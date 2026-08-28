<!-- =====================================================
     CHATBOT - À INCLURE DANS TOUTES LES PAGES CLIENT
     Utilisation : <?php include 'chatbot.php'; ?>
===================================================== -->

<?php if(!isset($chatbot_inclus)): $chatbot_inclus = true; ?>

<style>
    /* =====================================================
       CHATBOT : BOUTON FLOTTANT
    ===================================================== */

    .chatbot-button {
        position: fixed;
        right: 20px;
        bottom: 20px;
        width: 50px;
        height: 50px;
        border: none;
        border-radius: 50%;
        background: #6d1626;
        color: white;
        font-size: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 1050;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
        transition: .25s ease;
    }

    .chatbot-button:hover {
        background: #4e0f1c;
        transform: scale(1.05);
    }

    .chatbot-notification {
        position: absolute;
        top: -2px;
        right: -2px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #c9a24b;
        color: #1c1a19;
        font-size: 9px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
    }

    /* =====================================================
       FENETRE CHATBOT
    ===================================================== */

    .chatbot-window {
        position: fixed;
        right: 20px;
        bottom: 75px;
        width: 320px;
        height: 420px;
        z-index: 1049;
        display: none;
        overflow: hidden;
        border-radius: 14px;
        background: white;
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
    }

    .chatbot-window.active {
        display: flex;
        flex-direction: column;
    }

    /* =====================================================
       ENTETE CHATBOT
    ===================================================== */

    .chatbot-header {
        background: #1c1a19;
        color: white;
        padding: 10px 14px;
        border-bottom: 3px solid #6d1626;
    }

    .chatbot-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(255,255,255,.12);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    .chatbot-title {
        font-size: 13px;
        font-weight: 700;
    }

    .chatbot-status {
        font-size: 10px;
    }

    .chatbot-status span {
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #2ed573;
        margin-right: 4px;
    }

    /* =====================================================
       ZONE MESSAGES
    ===================================================== */

    .chatbot-messages {
        flex: 1;
        overflow-y: auto;
        padding: 10px;
        background: #f6f1ea;
    }

    .chatbot-message {
        display: flex;
        gap: 6px;
        margin-bottom: 10px;
    }

    .chatbot-mini-avatar {
        width: 26px;
        height: 26px;
        min-width: 26px;
        border-radius: 50%;
        background: #f1e4d8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }

    .chatbot-bubble {
        max-width: 85%;
        background: white;
        border-radius: 12px;
        border-top-left-radius: 3px;
        padding: 8px 10px;
        font-size: 12px;
        line-height: 1.4;
        color: #333;
        box-shadow: 0 1px 4px rgba(0,0,0,.05);
    }

    .chatbot-client {
        justify-content: flex-end;
    }

    .chatbot-client .chatbot-bubble {
        background: #6d1626;
        color: white;
        border-radius: 12px;
        border-top-right-radius: 3px;
    }

    /* =====================================================
       SUGGESTIONS
    ===================================================== */

    .chatbot-suggestions {
        padding: 6px 8px;
        display: flex;
        gap: 4px;
        overflow-x: auto;
        border-top: 1px solid #eee;
        background: white;
    }

    .chatbot-suggestions button {
        white-space: nowrap;
        border: 1px solid #e6d3d3;
        background: #fbf5f1;
        color: #6d1626;
        border-radius: 16px;
        padding: 4px 8px;
        font-size: 10px;
        cursor: pointer;
        transition: all .15s ease;
    }

    .chatbot-suggestions button:hover {
        background: #6d1626;
        color: white;
    }

    /* =====================================================
       SAISIE MESSAGE
    ===================================================== */

    .chatbot-footer {
        padding: 6px 10px;
        display: flex;
        gap: 6px;
        background: white;
        border-top: 1px solid #eee;
    }

    .chatbot-footer input {
        flex: 1;
        min-width: 0;
        border: 1px solid #ddd;
        border-radius: 20px;
        padding: 6px 12px;
        outline: none;
        font-size: 12px;
    }

    .chatbot-footer input:focus {
        border-color: #6d1626;
        box-shadow: 0 0 0 2px rgba(109,22,38,.08);
    }

    .chatbot-send {
        width: 34px;
        height: 34px;
        border: none;
        border-radius: 50%;
        background: #6d1626;
        color: white;
        font-size: 14px;
        cursor: pointer;
        transition: background .2s ease;
    }

    .chatbot-send:hover {
        background: #4e0f1c;
    }

    /* =====================================================
       MOBILE
    ===================================================== */

    @media (max-width: 576px) {
        .chatbot-button {
            right: 15px;
            bottom: 15px;
            width: 44px;
            height: 44px;
            font-size: 17px;
        }

        .chatbot-window {
            right: 10px;
            left: 10px;
            bottom: 65px;
            width: auto !important;
            height: 60vh !important;
            max-height: 400px;
            border-radius: 12px;
        }

        .chatbot-header {
            padding: 8px 12px !important;
        }

        .chatbot-title {
            font-size: 12px !important;
        }

        .chatbot-avatar {
            width: 28px;
            height: 28px;
            font-size: 13px;
        }

        .chatbot-messages {
            padding: 8px !important;
        }

        .chatbot-bubble {
            font-size: 11px !important;
            padding: 6px 8px !important;
            max-width: 90% !important;
        }

        .chatbot-suggestions {
            padding: 4px 6px !important;
            gap: 3px !important;
        }

        .chatbot-suggestions button {
            font-size: 9px !important;
            padding: 3px 6px !important;
        }

        .chatbot-footer {
            padding: 5px 8px !important;
            gap: 4px !important;
        }

        .chatbot-footer input {
            font-size: 11px !important;
            padding: 5px 10px !important;
        }

        .chatbot-send {
            width: 30px;
            height: 30px;
            font-size: 12px !important;
        }

        .chatbot-notification {
            width: 16px;
            height: 16px;
            font-size: 8px;
        }
    }
</style>

<!-- =====================================================
     BOUTON CHATBOT
===================================================== -->

<button
    type="button"
    id="chatbotButton"
    class="chatbot-button"
    onclick="ouvrirChatbot()"
    aria-label="Ouvrir l'assistant"
>

    <i class="bi bi-chat-dots-fill"></i>

    <span class="chatbot-notification">
        1
    </span>

</button>


<!-- =====================================================
     FENETRE CHATBOT
===================================================== -->

<div id="chatbotWindow" class="chatbot-window">

    <!-- HEADER -->
    <div class="chatbot-header">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="chatbot-avatar">🍷</div>
                <div>
                    <div class="chatbot-title">Assistant Cave à Vins</div>
                    <div class="chatbot-status"><span></span> En ligne</div>
                </div>
            </div>
            <button type="button" class="btn btn-sm text-white" onclick="fermerChatbot()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>

    <!-- MESSAGES -->
    <div id="chatbotMessages" class="chatbot-messages">
        <div class="chatbot-message chatbot-bot">
            <div class="chatbot-mini-avatar">🍷</div>
            <div class="chatbot-bubble">
                Bonjour 👋<br><br>
                Je suis votre assistant de la <strong>Cave à Vins</strong>.<br><br>
                Je peux vous aider à connaître les vins, les prix, les stocks, les promotions et vos commandes.<br><br>
                <strong>Que souhaitez-vous savoir ?</strong>
            </div>
        </div>
    </div>

    <!-- SUGGESTIONS -->
    <div class="chatbot-suggestions">
        <button type="button" onclick="questionChatbot('Quels vins avez-vous ?')">🍷 Vins</button>
        <button type="button" onclick="questionChatbot('Quel est le stock ?')">📦 Stock</button>
        <button type="button" onclick="questionChatbot('Quelles sont les promotions ?')">🏷️ Promotions</button>
        <button type="button" onclick="questionChatbot('Voir mes commandes')">🛒 Commandes</button>
    </div>

    <!-- SAISIE -->
    <div class="chatbot-footer">
        <input type="text" id="chatbotInput" class="form-control" placeholder="Écrire un message..." autocomplete="off">
        <button type="button" id="chatbotSend" class="chatbot-send" onclick="envoyerMessageChatbot()">
            <i class="bi bi-send-fill"></i>
        </button>
    </div>

</div>

<script>
/* =====================================================
   ELEMENTS CHATBOT
===================================================== */

const chatbotWindow = document.getElementById("chatbotWindow");
const chatbotInput = document.getElementById("chatbotInput");
const chatbotMessages = document.getElementById("chatbotMessages");
const chatbotSend = document.getElementById("chatbotSend");

/* =====================================================
   OUVRIR CHATBOT
===================================================== */

function ouvrirChatbot() {
    chatbotWindow.classList.add("active");
    chatbotInput.focus();
}

/* =====================================================
   FERMER CHATBOT
===================================================== */

function fermerChatbot() {
    chatbotWindow.classList.remove("active");
}

/* =====================================================
   PROTECTION TEXTE
===================================================== */

function securiserTexte(texte) {
    const element = document.createElement("div");
    element.textContent = texte;
    return element.innerHTML;
}

/* =====================================================
   MESSAGE CLIENT
===================================================== */

function ajouterMessageClient(texte) {
    const message = document.createElement("div");
    message.className = "chatbot-message chatbot-client";
    message.innerHTML = `<div class="chatbot-bubble">${securiserTexte(texte)}</div>`;
    chatbotMessages.appendChild(message);
    chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
}

/* =====================================================
   MESSAGE BOT
===================================================== */

function ajouterMessageBot(texte) {
    const message = document.createElement("div");
    message.className = "chatbot-message chatbot-bot";
    message.innerHTML = `
        <div class="chatbot-mini-avatar">🍷</div>
        <div class="chatbot-bubble">${texte}</div>
    `;
    chatbotMessages.appendChild(message);
    chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
}

/* =====================================================
   ENVOYER MESSAGE
===================================================== */

async function envoyerMessageChatbot() {
    const texte = chatbotInput.value.trim();
    if (texte === "") return;

    ajouterMessageClient(texte);
    chatbotInput.value = "";
    chatbotSend.disabled = true;

    const chargement = document.createElement("div");
    chargement.id = "chatbotChargement";
    chargement.className = "chatbot-message chatbot-bot";
    chargement.innerHTML = `
        <div class="chatbot-mini-avatar">🍷</div>
        <div class="chatbot-bubble">
            <span class="spinner-border spinner-border-sm me-2"></span>
            Je réfléchis...
        </div>
    `;
    chatbotMessages.appendChild(chargement);
    chatbotMessages.scrollTop = chatbotMessages.scrollHeight;

    try {
        const donnees = new FormData();
        donnees.append("message", texte);

        const reponse = await fetch("chatbot_api.php", {
            method: "POST",
            body: donnees
        });

        const resultat = await reponse.json();

        const chargementActuel = document.getElementById("chatbotChargement");
        if (chargementActuel) chargementActuel.remove();

        if (resultat.success) {
            ajouterMessageBot(resultat.response);
        } else {
            ajouterMessageBot("⚠️ " + (resultat.message || "Une erreur est survenue."));
        }
    } catch (erreur) {
        console.error("Erreur Chatbot:", erreur);
        const chargementActuel = document.getElementById("chatbotChargement");
        if (chargementActuel) chargementActuel.remove();
        ajouterMessageBot("❌ Impossible de contacter le serveur.");
    }

    chatbotSend.disabled = false;
    chatbotInput.focus();
}

/* =====================================================
   QUESTION RAPIDE
===================================================== */

function questionChatbot(question) {
    chatbotInput.value = question;
    envoyerMessageChatbot();
}

/* =====================================================
   TOUCHE ENTRÉE
===================================================== */

chatbotInput.addEventListener("keydown", function(event) {
    if (event.key === "Enter") {
        event.preventDefault();
        envoyerMessageChatbot();
    }
});
</script>

<?php endif; ?>