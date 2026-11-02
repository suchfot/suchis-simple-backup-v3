document.addEventListener("DOMContentLoaded", function () {
    const button = document.getElementById("ssb-start-backup");
    const analysisStart = document.getElementById("ssb-analysis-start");
    const overlay = document.getElementById("ssb-backup-overlay");
    const status = document.getElementById("ssb-overlay-status");
    const analysisOverlay = document.getElementById("ssb-analysis-overlay");
    const analysisTitle = document.getElementById("ssb-analysis-title");
    const analysisMessage = document.getElementById("ssb-analysis-message");
    const analysisStatus = document.getElementById("ssb-analysis-status");
    const analysisProgress = document.getElementById("ssb-analysis-progress");
    const analysisSummary = document.getElementById("ssb-analysis-summary");
    const analysisFiles = document.getElementById("ssb-analysis-files");
    const analysisBytes = document.getElementById("ssb-analysis-bytes");
    const analysisZipBytes = document.getElementById("ssb-analysis-zip-bytes");
    const analysisTime = document.getElementById("ssb-analysis-time");
    const analysisHint = document.getElementById("ssb-analysis-hint");
    const analysisError = document.getElementById("ssb-analysis-error");
    const analysisClose = document.getElementById("ssb-analysis-close");
    let analysisJobId = null;

    document.querySelectorAll(".ssb-checks__toggle").forEach(function (toggle) {
        const panel = document.getElementById(toggle.getAttribute("aria-controls"));
        if (!panel) return;
        toggle.addEventListener("click", function () {
            const expanded = toggle.getAttribute("aria-expanded") === "true";
            toggle.setAttribute("aria-expanded", expanded ? "false" : "true");
            panel.hidden = expanded;
        });
    });

    if (!button || !overlay) return;

    const titleText = overlay.querySelector(".ssb-overlay__title-text");
    const closeButton = document.getElementById("ssb-overlay-close");
    const discardButton = document.getElementById("ssb-backup-discard");
    const helpToggle = document.getElementById("ssb-error-help-toggle");
    const help = document.getElementById("ssb-error-help");
    const deleteModal = document.getElementById("ssb-delete-modal");
    const deleteName = document.getElementById("ssb-delete-name");
    const deleteConfirm = document.getElementById("ssb-delete-confirm");
    const deleteCancel = document.getElementById("ssb-delete-cancel");

    const showOverlay = function (message) {
        status.textContent = message;
        overlay.classList.add("is-visible");
        button.classList.add("disabled");
        button.disabled = true;
        button.setAttribute("aria-disabled", "true");
    };

    const renderProgress = function (data) {
        status.textContent = "";
        const progress = document.createElement("div");
        progress.className = "ssb-progress";
        const addRow = function (icon, label, state) {
            const row = document.createElement("div");
            row.className = "ssb-progress__row " + state;
            const iconElement = document.createElement("span");
            iconElement.className = "dashicons " + icon + " ssb-progress__icon";
            iconElement.setAttribute("aria-hidden", "true");
            const labelElement = document.createElement("span");
            labelElement.className = "ssb-progress__label";
            labelElement.textContent = label;
            row.appendChild(iconElement);
            row.appendChild(labelElement);
            progress.appendChild(row);
        };

        if (data.step === "analysis") {
            const scanned = Number(data.files || 0).toLocaleString("de-DE");
            const total = Number(data.files_total || 0).toLocaleString("de-DE");
            const label = data.files_total
                ? "Dateien geprüft: " + scanned + " von " + total
                : "Dateien geprüft: " + scanned;
            addRow("dashicons-open-folder", label, "is-active");
            addRow("dashicons-database", "Datenbank wird danach gesichert", "");
        } else if (["files", "directory", "verify", "publish"].includes(data.step)) {
            addRow("dashicons-yes-alt", data.database_message || "Datenbank gesichert", "is-done");
            addRow("dashicons-open-folder", "Dateien geschrieben: " + Number(data.processed_files || 0).toLocaleString("de-DE") + " von " + Number(data.files || 0).toLocaleString("de-DE"), "is-active");
            if (data.current_file) {
                addRow("dashicons-media-default", data.current_file + " – " + formatBytes(data.current_file_bytes), "is-active");
            }
            addRow("dashicons-database", "ZIP64 ohne Komprimierung: " + formatBytes(data.archive_bytes), "");
            if (data.step === "verify" || data.step === "publish") {
                addRow("dashicons-search", "Integritätsprüfung: " + Number(data.verified_entries || 0).toLocaleString("de-DE") + " Einträge geprüft", "is-active");
            } else if (data.step === "directory") {
                addRow("dashicons-search", "ZIP-Verzeichnis wird abgeschlossen", "is-active");
            }
        } else {
            addRow("dashicons-database", "Schritt 1 von 2: Datenbank wird gesichert ...", "is-active");
            addRow("dashicons-open-folder", "Schritt 2 von 2: Dateien werden danach gepackt", "");
        }
        status.appendChild(progress);
    };

    const renderFailure = function (data) {
        overlay.classList.add("has-error");
        titleText.textContent = "Backup konnte nicht erstellt werden";
        status.textContent = (data.message || "Unbekannter Fehler beim Erstellen des Backups.")
            + (data.job_id ? " Auftrags-ID für das Diagnoseprotokoll: " + data.job_id : "");
        closeButton.style.display = "inline-block";
        button.textContent = "Backup fortsetzen / erneut versuchen";
        discardButton.hidden = false;
    };

    const request = function (action, data) {
        const form = new FormData();
        form.append("action", action);
        form.append("nonce", ssbhfAdmin.nonce);
        Object.keys(data || {}).forEach(function (key) { form.append(key, data[key]); });
        return fetch(ssbhfAdmin.ajaxUrl, { method: "POST", credentials: "same-origin", body: form }).then(function (response) {
            if (response.status === 403) {
                const error = new Error("Sitzung oder Sicherheitscode abgelaufen. Seite neu laden; der Auftrag bleibt gespeichert.");
                error.sessionExpired = true;
                throw error;
            }
            return response.json();
        });
    };

    const formatBytes = function (bytes) {
        const units = ["Bytes", "KB", "MB", "GB", "TB"];
        let value = Number(bytes) || 0;
        let unit = 0;
        while (value >= 1024 && unit < units.length - 1) {
            value /= 1024;
            unit++;
        }
        return value.toLocaleString("de-DE", { maximumFractionDigits: 1 }) + " " + units[unit];
    };

    const finishAnalysis = function (data) {
        analysisTitle.textContent = "Deine Installation wurde untersucht";
        analysisMessage.textContent = "Die Analyse ist abgeschlossen.";
        analysisStatus.textContent = "";
        analysisProgress.hidden = true;
        analysisSummary.hidden = false;
        analysisFiles.textContent = Number(data.files || 0).toLocaleString("de-DE");
        analysisBytes.textContent = formatBytes(data.bytes);
        analysisZipBytes.textContent = "ca. " + formatBytes(data.estimated_zip_bytes);
        analysisTime.textContent = data.estimated_duration || "ca. 1–3 Minuten";
        analysisHint.hidden = false;
        analysisClose.hidden = false;
        analysisClose.focus();
    };

    const failAnalysis = function (message) {
        analysisTitle.textContent = "Dateianalyse nicht möglich";
        analysisMessage.textContent = "Die Installation konnte vor dem Backup nicht vollständig untersucht werden.";
        analysisStatus.textContent = "";
        analysisProgress.hidden = true;
        analysisError.textContent = message;
        analysisError.hidden = false;
        analysisClose.hidden = false;
        analysisStart.disabled = false;
        analysisClose.focus();
    };

    const pollAnalysis = function (jobId, failures) {
        request("ssbhf_analyze_chunk", { job_id: jobId }).then(function (response) {
            if (!response.success) throw new Error(response.data || "Status konnte nicht gelesen werden.");
            const data = response.data;
            if (data.status === "done") {
                finishAnalysis(data);
                return;
            }
            if (data.status === "failed") {
                failAnalysis(data.message || "Unbekannter Fehler bei der Dateianalyse.");
                return;
            }
            analysisStatus.textContent = Number(data.files || 0).toLocaleString("de-DE")
                + " Dateien geprüft, " + formatBytes(data.bytes || 0) + " erfasst ...";
            window.setTimeout(function () { pollAnalysis(jobId, 0); }, 1000);
        }).catch(function (error) {
            const nextFailures = failures + 1;
            analysisStatus.textContent = "Statusabfrage unterbrochen: " + error.message + ". Die Analyse läuft möglicherweise weiter; erneuter Versuch ...";
            window.setTimeout(function () {
                pollAnalysis(jobId, nextFailures);
            }, Math.min(15000, 2000 * nextFailures));
        });
    };

    if (analysisOverlay) {
        analysisClose.addEventListener("click", function () {
            analysisOverlay.classList.remove("is-visible");
            analysisStart.disabled = false;
            analysisStart.focus();
        });
        analysisStart.addEventListener("click", function () {
            analysisJobId = null;
            analysisStart.disabled = true;
            analysisTitle.textContent = "Bitte warten: Deine Installation wird untersucht";
            analysisMessage.textContent = "Die Dateien deiner Installation werden gezählt und ihre Größe wird ermittelt.";
            analysisStatus.textContent = "Analyse wird gestartet ...";
            analysisProgress.hidden = false;
            analysisSummary.hidden = true;
            analysisHint.hidden = true;
            analysisError.hidden = true;
            analysisError.textContent = "";
            analysisClose.hidden = true;
            analysisOverlay.classList.add("is-visible");
            request("ssbhf_analyze", {}).then(function (response) {
                if (!response.success) throw new Error(response.data || "Dateianalyse konnte nicht gestartet werden.");
                analysisJobId = response.data.job_id;
                pollAnalysis(response.data.job_id, 0);
            }).catch(function (error) {
                failAnalysis(error.message);
            });
        });
    }

    let statusPollFailures = 0;
    const poll = function (jobId) {
        request("ssbhf_backup_chunk", { job_id: jobId }).then(function (response) {
            if (!response.success) throw new Error(response.data || "Status konnte nicht gelesen werden.");
            statusPollFailures = 0;
            const data = response.data;
            if (data.status === "busy") {
                status.textContent = data.message;
                window.setTimeout(function () { poll(jobId); }, 1500);
                return;
            }
            if (data.status === "cancelled") {
                window.location.reload();
                return;
            }
            if (data.status === "done") {
                window.location.href = data.redirect;
                return;
            }
            if (data.status === "failed") {
                renderFailure(data);
                return;
            }
            renderProgress(data);
            window.setTimeout(function () { poll(jobId); }, 300);
        }).catch(function (error) {
            if (error.sessionExpired) {
                renderFailure({ message: error.message });
                return;
            }
            statusPollFailures++;
            status.textContent = "Die Statusabfrage ist vorübergehend unterbrochen. Das Backup kann im Hintergrund weiterlaufen; wir versuchen es in Kürze erneut. (" + error.message + ")";
            window.setTimeout(function () { poll(jobId); }, Math.min(15000, 2000 * statusPollFailures));
        });
    };

    // The server persists the active job; no browser-local state is required.
    // Merely closing this page pauses processing; reload resumes committed work.
    if (ssbhfAdmin.activeJobId) {
        showOverlay("Gespeicherter Backupauftrag wird fortgesetzt ...");
        if (ssbhfAdmin.activeStatus && ssbhfAdmin.activeStatus.status === "failed") {
            renderFailure(ssbhfAdmin.activeStatus);
        } else {
            poll(ssbhfAdmin.activeJobId);
        }
    }

    button.addEventListener("click", function () {
        showOverlay("Backup wird jetzt gestartet ...");
        request("ssbhf_start", { analysis_job_id: analysisJobId || "" }).then(function (response) {
            if (!response.success) throw new Error(response.data || "Backup konnte nicht gestartet werden.");
            renderProgress(response.data);
            poll(response.data.job_id);
        }).catch(function (error) {
            renderFailure({ message: error.message });
            button.classList.remove("disabled");
            button.disabled = false;
            button.removeAttribute("aria-disabled");
        });
    });

    discardButton.addEventListener("click", function () {
        if (!window.confirm("Unfertiges Backup wirklich verwerfen? Bereits fertige Backups bleiben erhalten.")) return;
        discardButton.disabled = true;
        request("ssbhf_cancel", {}).then(function (response) {
            if (!response.success) throw new Error(response.data || "Verwerfen fehlgeschlagen.");
            window.location.reload();
        }).catch(function (error) {
            status.textContent = error.message;
            discardButton.disabled = false;
        });
    });

    closeButton.addEventListener("click", function () {
        overlay.classList.remove("is-visible", "has-error");
        closeButton.style.display = "none";
        discardButton.hidden = true;
        titleText.textContent = "Backup wird jetzt erstellt";
        help.hidden = true;
        helpToggle.setAttribute("aria-expanded", "false");
        button.classList.remove("disabled");
        button.disabled = false;
        button.removeAttribute("aria-disabled");
    });

    helpToggle.addEventListener("click", function () {
        help.hidden = !help.hidden;
        helpToggle.setAttribute("aria-expanded", help.hidden ? "false" : "true");
    });

    document.querySelectorAll(".ssb-delete-backup").forEach(function (deleteLink) {
        deleteLink.addEventListener("click", function (event) {
            event.preventDefault();
            deleteName.textContent = deleteLink.getAttribute("data-file") || "dieses Backup";
            deleteConfirm.href = deleteLink.href;
            deleteModal.classList.add("is-visible");
            deleteConfirm.focus();
        });
    });

    const closeDeleteModal = function () {
        deleteModal.classList.remove("is-visible");
    };

    deleteCancel.addEventListener("click", closeDeleteModal);
    deleteModal.addEventListener("click", function (event) {
        if (event.target === deleteModal) closeDeleteModal();
    });
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && deleteModal.classList.contains("is-visible")) closeDeleteModal();
    });
});
