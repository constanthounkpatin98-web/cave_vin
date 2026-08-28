/*
    sonnerie_admin.js
    ------------------
    Sonnerie + animation de la cloche quand une nouvelle notification
    (ex: nouveau paiement) arrive côté admin.

    A placer dans un dossier commun accessible depuis toutes les pages admin,
    par exemple : /assets/js/sonnerie_admin.js

    A inclure dans CHAQUE page admin (juste avant la fermeture </body>,
    après bootstrap.bundle.min.js), avec :

        <script src="../assets/js/sonnerie_admin.js"></script>

    Prérequis dans le HTML de la page :
      - un élément avec la classe "cloche-notif" (le bouton cloche du topbar)
      - CSS de l'animation .cloche-secoue (voir plus bas, à ajouter une seule fois
        dans ta feuille de style commune si tu en as une, sinon dans chaque page)
*/

(function () {

    // Chemin vers l'endpoint : toujours "../verifier_notifications.php"
    // car verifier_notifications.php est à la racine et chaque page admin
    // est dans un sous-dossier (commande/, paiement/, tableau_bord/, etc.)
    const URL_VERIFICATION = "../verifier_notifications.php";
    const INTERVALLE_MS = 8000; // vérifie toutes les 8 secondes
    const DEBUG = true; // mets à false une fois que ça marche, pour ne plus polluer la console

    let dernierIdNotifConnu = null;
    let premierePasse = true;
    let contexteAudio = null;
    let audioDebloque = false;

    // ---------------------------------------------------------
    // Débloquer le son : les navigateurs interdisent de jouer
    // un son tant que l'utilisateur n'a pas interagi avec la page
    // (clic, touche, etc.). On "prépare" donc le contexte audio
    // dès le premier clic n'importe où sur la page.
    // ---------------------------------------------------------
    function debloquerAudio() {
        if (audioDebloque) return;
        try {
            contexteAudio = new (window.AudioContext || window.webkitAudioContext)();
            if (contexteAudio.state === "suspended") {
                contexteAudio.resume();
            }
            audioDebloque = true;
            if (DEBUG) console.log("[sonnerie_admin] Audio débloqué avec succès.");
        } catch (e) {
            if (DEBUG) console.warn("[sonnerie_admin] Impossible de débloquer l'audio :", e);
        }
    }

    document.addEventListener("click", debloquerAudio, { once: true });
    document.addEventListener("keydown", debloquerAudio, { once: true });

    function jouerSonnerie() {
        if (!contexteAudio) {
            if (DEBUG) console.warn("[sonnerie_admin] Contexte audio non prêt (l'admin n'a pas encore cliqué sur la page) — le son ne peut pas être joué par le navigateur.");
            return;
        }
        if (contexteAudio.state === "suspended") {
            contexteAudio.resume();
        }

        const maintenant = contexteAudio.currentTime;
        const volume = 0.5;

        // ---- 1. "Thump" grave (impact synthé, façon notification premium) ----
        const basse = contexteAudio.createOscillator();
        const gainBasse = contexteAudio.createGain();
        basse.connect(gainBasse);
        gainBasse.connect(contexteAudio.destination);
        basse.type = "sine";
        basse.frequency.setValueAtTime(180, maintenant);
        basse.frequency.exponentialRampToValueAtTime(60, maintenant + 0.18);
        gainBasse.gain.setValueAtTime(volume * 0.9, maintenant);
        gainBasse.gain.exponentialRampToValueAtTime(0.0001, maintenant + 0.22);
        basse.start(maintenant);
        basse.stop(maintenant + 0.25);

        // ---- 2. Petit "sweep" synthé qui monte (effet cool / futuriste) ----
        const sweep = contexteAudio.createOscillator();
        const gainSweep = contexteAudio.createGain();
        const filtreSweep = contexteAudio.createBiquadFilter();
        sweep.connect(filtreSweep);
        filtreSweep.connect(gainSweep);
        gainSweep.connect(contexteAudio.destination);
        filtreSweep.type = "lowpass";
        filtreSweep.frequency.setValueAtTime(400, maintenant + 0.1);
        filtreSweep.frequency.exponentialRampToValueAtTime(4000, maintenant + 0.45);
        sweep.type = "sawtooth";
        sweep.frequency.setValueAtTime(220, maintenant + 0.1);
        sweep.frequency.exponentialRampToValueAtTime(880, maintenant + 0.45);
        gainSweep.gain.setValueAtTime(0.0001, maintenant + 0.1);
        gainSweep.gain.exponentialRampToValueAtTime(volume * 0.35, maintenant + 0.2);
        gainSweep.gain.exponentialRampToValueAtTime(0.0001, maintenant + 0.5);
        sweep.start(maintenant + 0.1);
        sweep.stop(maintenant + 0.5);

        // ---- 3. Petit arpège "sparkle" façon jeu vidéo, en fin de sonnerie ----
        const notesSparkle = [1046.5, 1318.5, 1567.98]; // Do6, Mi6, Sol6
        notesSparkle.forEach(function (freq, i) {
            const debut = maintenant + 0.42 + i * 0.09;
            const osc = contexteAudio.createOscillator();
            const gain = contexteAudio.createGain();
            osc.connect(gain);
            gain.connect(contexteAudio.destination);
            osc.type = "square";
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, debut);
            gain.gain.exponentialRampToValueAtTime(volume * 0.4, debut + 0.015);
            gain.gain.exponentialRampToValueAtTime(0.0001, debut + 0.14);
            osc.start(debut);
            osc.stop(debut + 0.16);
        });

        if (DEBUG) console.log("[sonnerie_admin] 🔔✨ Sonnerie (style synthé) jouée.");
    }

    function verifierNouvellesNotifications() {
        fetch(URL_VERIFICATION)
            .then(function (reponse) {
                if (DEBUG) console.log("[sonnerie_admin] Statut HTTP :", reponse.status);
                return reponse.text();
            })
            .then(function (texteBrut) {
                let donnees;
                try {
                    donnees = JSON.parse(texteBrut);
                } catch (e) {
                    console.error("[sonnerie_admin] Réponse non-JSON reçue (vérifie que verifier_notifications.php est bien à la racine du projet, au même niveau que connexion.php). Réponse brute :", texteBrut);
                    return;
                }

                if (donnees.erreur) {
                    if (DEBUG) console.warn("[sonnerie_admin] Erreur renvoyée par le serveur :", donnees.erreur);
                    return;
                }

                if (DEBUG) console.log("[sonnerie_admin] Données reçues :", donnees, "| dernier ID connu :", dernierIdNotifConnu);

                const cloche = document.querySelector(".cloche-notif");
                let badge = document.querySelector(".cloche-notif .point-badge");

                if (donnees.non_lues > 0) {
                    if (!badge && cloche) {
                        badge = document.createElement("span");
                        badge.className = "point-badge";
                        cloche.appendChild(badge);
                    }
                    if (badge) badge.textContent = Math.min(donnees.non_lues, 99);
                } else if (badge) {
                    badge.remove();
                }

                if (dernierIdNotifConnu === null) {
                    dernierIdNotifConnu = donnees.dernier_id;
                }

                if (!premierePasse && donnees.dernier_id > dernierIdNotifConnu) {
                    jouerSonnerie();
                    if (cloche) {
                        cloche.classList.add("cloche-secoue");
                        setTimeout(function () { cloche.classList.remove("cloche-secoue"); }, 1000);
                    }
                }

                dernierIdNotifConnu = donnees.dernier_id;
                premierePasse = false;
            })
            .catch(function (e) {
                console.error("[sonnerie_admin] Échec de la requête vers verifier_notifications.php :", e);
            });
    }

    document.addEventListener("DOMContentLoaded", function () {
        verifierNouvellesNotifications();
        setInterval(verifierNouvellesNotifications, INTERVALLE_MS);
    });

})();