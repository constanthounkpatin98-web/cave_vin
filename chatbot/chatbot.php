<?php

session_start();

require_once "../connexion.php";

?>

<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Assistant - Cave à vins</title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="chatbot.css"
    >

</head>

<body>


<div class="chat-container">


    <!-- EN-TÊTE -->

    <div class="chat-header">

        <div class="bot-avatar">
            🍷
        </div>

        <div>

            <h5>
                Assistant Gestion des Vins
            </h5>

            <span>
                <i></i>
                En ligne
            </span>

        </div>

    </div>


    <!-- CORPS DU CHAT -->

    <div
        class="chat-body"
        id="chatMessages"
    >

        <div class="message bot">

            <div class="avatar">
                🍷
            </div>

            <div class="bubble">

                Bonjour 👋

                <br><br>

                Bienvenue sur votre espace
                <strong>Cave à vins</strong>.

                <br><br>

                Je peux vous aider à consulter
                les vins, les prix, le stock,
                les promotions, vos commandes,
                vos livraisons et vos paiements.

                <br><br>

                <strong>Que souhaitez-vous savoir ?</strong>

            </div>

        </div>

    </div>


    <!-- SUGGESTIONS -->

    <div class="suggestions">

        <button onclick="questionRapide('Quels vins avez-vous ?')">
            🍷 Les vins
        </button>

        <button onclick="questionRapide('Quel est le stock ?')">
            📦 Stock
        </button>

        <button onclick="questionRapide('Quelles sont les promotions ?')">
            🏷️ Promotions
        </button>

        <button onclick="questionRapide('Voir mes commandes')">
            🛒 Commandes
        </button>

    </div>


    <!-- SAISIE -->

    <div class="chat-footer">

        <input
            type="text"
            id="messageInput"
            placeholder="Écrivez votre message..."
            autocomplete="off"
        >

        <button
            id="sendButton"
            onclick="envoyerMessage()"
        >
            ➤
        </button>

    </div>


</div>


<script>

const input = document.getElementById("messageInput");

const button = document.getElementById("sendButton");

const messages = document.getElementById("chatMessages");


/*
|--------------------------------------------------------------------------
| PROTECTION HTML
|--------------------------------------------------------------------------
*/

function securiserTexte(texte) {

    const div = document.createElement("div");

    div.textContent = texte;

    return div.innerHTML;
}


/*
|--------------------------------------------------------------------------
| AJOUTER MESSAGE
|--------------------------------------------------------------------------
*/

function ajouterMessage(texte, type) {

    const bloc = document.createElement("div");

    bloc.className = "message " + type;


    let contenu = "";


    if (type === "bot") {

        contenu = `
            <div class="avatar">
                🍷
            </div>

            <div class="bubble">
                ${texte}
            </div>
        `;

    } else {

        contenu = `
            <div class="bubble">
                ${securiserTexte(texte)}
            </div>
        `;
    }


    bloc.innerHTML = contenu;

    messages.appendChild(bloc);

    messages.scrollTop = messages.scrollHeight;
}


/*
|--------------------------------------------------------------------------
| INDICATEUR "ÉCRIT..."
|--------------------------------------------------------------------------
*/

function afficherChargement() {

    const chargement = document.createElement("div");

    chargement.id = "chargement";

    chargement.className = "message bot";

    chargement.innerHTML = `
        <div class="avatar">
            🍷
        </div>

        <div class="bubble typing">
            <span></span>
            <span></span>
            <span></span>
        </div>
    `;

    messages.appendChild(chargement);

    messages.scrollTop = messages.scrollHeight;
}


/*
|--------------------------------------------------------------------------
| SUPPRIMER CHARGEMENT
|--------------------------------------------------------------------------
*/

function supprimerChargement() {

    const chargement =
        document.getElementById("chargement");

    if (chargement) {

        chargement.remove();

    }
}


/*
|--------------------------------------------------------------------------
| ENVOYER
|--------------------------------------------------------------------------
*/

async function envoyerMessage() {

    const texte = input.value.trim();


    if (texte === "") {

        return;

    }


    ajouterMessage(
        texte,
        "client"
    );


    input.value = "";

    button.disabled = true;

    input.disabled = true;


    afficherChargement();


    try {

        const donnees = new FormData();

        donnees.append(
            "message",
            texte
        );


        const response = await fetch(
            "chatbot_api.php",
            {
                method: "POST",
                body: donnees
            }
        );


        const resultat = await response.json();


        supprimerChargement();


        if (resultat.success) {

            ajouterMessage(
                resultat.response,
                "bot"
            );

        } else {

            ajouterMessage(
                "⚠️ " +
                (
                    resultat.message ||
                    "Une erreur est survenue."
                ),
                "bot"
            );
        }


    } catch (erreur) {

        console.error(erreur);


        supprimerChargement();


        ajouterMessage(
            "❌ Impossible de contacter le serveur. Vérifiez votre connexion à la base de données.",
            "bot"
        );

    }


    button.disabled = false;

    input.disabled = false;

    input.focus();

}


/*
|--------------------------------------------------------------------------
| QUESTION RAPIDE
|--------------------------------------------------------------------------
*/

function questionRapide(question) {

    input.value = question;

    envoyerMessage();

}


/*
|--------------------------------------------------------------------------
| TOUCHE ENTRÉE
|--------------------------------------------------------------------------
*/

input.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Enter") {

            event.preventDefault();

            envoyerMessage();

        }

    }
);

</script>


</body>

</html>