<?php

session_start();

require_once "../connexion.php";

header("Content-Type: application/json; charset=UTF-8");

/*
|--------------------------------------------------------------------------
| IDENTIFICATION DU CLIENT (MÊME NON CONNECTÉ)
|--------------------------------------------------------------------------
*/

$id_client = null;

if (isset($_SESSION['client_id'])) {
    $id_client = (int) $_SESSION['client_id'];
} elseif (isset($_SESSION['id_client'])) {
    $id_client = (int) $_SESSION['id_client'];
}

// Le reste du code reste identique...
// Le chatbot fonctionne même si $id_client est null (visiteur non connecté)

/*
|--------------------------------------------------------------------------
| RÉCUPÉRATION DU CHATBOT
|--------------------------------------------------------------------------
*/

try {
    $requeteBot = $connexion->query("
        SELECT id_chatbot, nom, version
        FROM chatbot
        ORDER BY id_chatbot ASC
        LIMIT 1
    ");
    $chatbot = $requeteBot->fetch();
} catch(PDOException $e) {
    $chatbot = [
        'id_chatbot' => 1,
        'nom' => 'Assistant Cave à Vins',
        'version' => '1.0'
    ];
}

if (!$chatbot) {
    $chatbot = [
        'id_chatbot' => 1,
        'nom' => 'Assistant Cave à Vins',
        'version' => '1.0'
    ];
}

$id_chatbot = (int) $chatbot['id_chatbot'];

/*
|--------------------------------------------------------------------------
| FONCTION D'ENREGISTREMENT DES MESSAGES
|--------------------------------------------------------------------------
*/

function enregistrerMessage(
    PDO $connexion,
    string $contenu,
    string $type_message,
    ?int $id_client,
    ?int $id_chatbot
) {
    try {
        $sql = "
            INSERT INTO message
            (contenu, expediteur, type_message, id_client, id_chatbot, date_envoi)
            VALUES
            (:contenu, :expediteur, :type_message, :id_client, :id_chatbot, NOW())
        ";
        $stmt = $connexion->prepare($sql);
        $stmt->execute([
            ':contenu' => $contenu,
            ':expediteur' => $type_message === "Client" ? "Client" : "Chatbot",
            ':type_message' => $type_message,
            ':id_client' => $id_client,
            ':id_chatbot' => $id_chatbot
        ]);
    } catch(PDOException $e) {
        // Ignorer si la table n'existe pas
    }
}

/*
|--------------------------------------------------------------------------
| MESSAGE REÇU
|--------------------------------------------------------------------------
*/

$message = trim($_POST['message'] ?? '');

if ($message === '') {
    echo json_encode([
        "success" => false,
        "message" => "Veuillez saisir un message."
    ]);
    exit;
}

// Enregistrer le message du client
enregistrerMessage($connexion, $message, "Client", $id_client, $id_chatbot);

/*
|--------------------------------------------------------------------------
| NORMALISATION DE LA QUESTION
|--------------------------------------------------------------------------
*/

$question = mb_strtolower($message, 'UTF-8');
$question = str_replace(
    ['à','â','ä','é','è','ê','ë','î','ï','ô','ö','ù','û','ü','ç'],
    ['a','a','a','e','e','e','e','i','i','o','o','u','u','u','c'],
    $question
);

$reponse = "";

/*
|--------------------------------------------------------------------------
| RÉPONSES
|--------------------------------------------------------------------------
*/

// Salutation
if (str_contains($question, 'bonjour') || str_contains($question, 'salut') || 
    str_contains($question, 'bonsoir') || str_contains($question, 'hello')) {
    $prenom = "";
    if ($id_client) {
        try {
            $stmt = $connexion->prepare("SELECT prenom FROM client WHERE id_client = ? LIMIT 1");
            $stmt->execute([$id_client]);
            $client = $stmt->fetch();
            if ($client) {
                $prenom = " " . htmlspecialchars($client['prenom'], ENT_QUOTES, 'UTF-8');
            }
        } catch(PDOException $e) {}
    }
    $reponse = "Bonjour" . $prenom . " 👋<br><br>Je suis <strong>l'Assistant Cave à Vins</strong> 🍷.<br><br>Je peux vous aider à consulter les vins, les prix, les stocks, les promotions, vos commandes, vos livraisons et vos paiements.<br><br>Que souhaitez-vous savoir ?";
}

// Aide
elseif (str_contains($question, 'aide') || str_contains($question, 'que peux tu faire') || 
        str_contains($question, 'fonction') || str_contains($question, 'commande possible')) {
    $reponse = "<strong>🤖 Voici ce que je peux faire :</strong><br><br>" .
        "🍷 <strong>Vins</strong><br>Demandez : « Quels vins avez-vous ? »<br><br>" .
        "💰 <strong>Prix</strong><br>Demandez : « Quel est le prix des vins ? »<br><br>" .
        "📦 <strong>Stock</strong><br>Demandez : « Quel est le stock ? »<br><br>" .
        "🏷️ <strong>Promotions</strong><br>Demandez : « Quelles sont les promotions ? »<br><br>" .
        "🛒 <strong>Commandes</strong><br>Demandez : « Voir mes commandes »<br><br>" .
        "🚚 <strong>Livraison</strong><br>Demandez : « Où est ma livraison ? »<br><br>" .
        "💳 <strong>Paiement</strong><br>Demandez : « Voir mes paiements »";
}

// Vins / Prix
elseif (str_contains($question, 'vin') || str_contains($question, 'prix') || 
        str_contains($question, 'produit') || str_contains($question, 'catalogue')) {
    try {
        $stmt = $connexion->query("
            SELECT v.nom_vin, v.millesime, v.pays_origine, v.couleur, v.prix, v.quantite_stock, c.libelle AS categorie
            FROM vin v
            LEFT JOIN categorie c ON c.id_categorie = v.id_categorie
            WHERE v.statut = 'Disponible'
            ORDER BY v.nom_vin ASC
            LIMIT 20
        ");
        $vins = $stmt->fetchAll();
        if (!$vins) {
            $reponse = "🍷 Aucun vin disponible actuellement.";
        } else {
            $reponse = "<strong>🍷 Nos vins disponibles :</strong><br><br>";
            $compteur = 0;
            foreach ($vins as $vin) {
                $compteur++;
                $nom = htmlspecialchars($vin['nom_vin'], ENT_QUOTES, 'UTF-8');
                $couleur = htmlspecialchars($vin['couleur'], ENT_QUOTES, 'UTF-8');
                $pays = htmlspecialchars($vin['pays_origine'] ?? 'Non précisé', ENT_QUOTES, 'UTF-8');
                $prix = number_format((float) $vin['prix'], 0, ',', ' ');
                $stock = (int) $vin['quantite_stock'];
                $reponse .= "🍷 <strong>" . $nom . "</strong><br>Prix : <strong>" . $prix . " FCFA</strong><br>Stock : " . $stock . " bouteille(s)<br><br>";
                if ($compteur >= 10) {
                    $reponse .= "<em>... et d'autres vins disponibles. Consultez notre catalogue !</em>";
                    break;
                }
            }
        }
    } catch(PDOException $e) {
        $reponse = "🍷 Désolé, je n'arrive pas à récupérer la liste des vins pour le moment.";
    }
}

// Stock
elseif (str_contains($question, 'stock') || str_contains($question, 'bouteille') || 
        str_contains($question, 'disponibilite') || str_contains($question, 'disponible')) {
    try {
        $stmt = $connexion->query("
            SELECT nom_vin, quantite_stock FROM vin WHERE statut <> 'Archivé' ORDER BY quantite_stock ASC LIMIT 15
        ");
        $stocks = $stmt->fetchAll();
        if (!$stocks) {
            $reponse = "📦 Aucun stock disponible.";
        } else {
            $reponse = "<strong>📦 État du stock :</strong><br><br>";
            foreach ($stocks as $stock) {
                $nom = htmlspecialchars($stock['nom_vin'], ENT_QUOTES, 'UTF-8');
                $quantite = (int) $stock['quantite_stock'];
                $icone = $quantite <= 0 ? "🔴" : ($quantite <= 5 ? "🟠" : "🟢");
                $reponse .= $icone . " <strong>" . $nom . "</strong><br>Quantité : " . $quantite . "<br><br>";
            }
        }
    } catch(PDOException $e) {
        $reponse = "📦 Désolé, je n'arrive pas à récupérer les stocks pour le moment.";
    }
}

// Promotions
elseif (str_contains($question, 'promotion') || str_contains($question, 'promo') || 
        str_contains($question, 'remise') || str_contains($question, 'reduction')) {
    try {
        $stmt = $connexion->query("
            SELECT libelle, description, type_remise, valeur_remise, date_debut, date_fin
            FROM promotion WHERE statut = 'Active' AND CURDATE() BETWEEN date_debut AND date_fin
            ORDER BY date_fin ASC
        ");
        $promotions = $stmt->fetchAll();
        if (!$promotions) {
            $reponse = "🏷️ Aucune promotion active actuellement.";
        } else {
            $reponse = "<strong>🏷️ Promotions disponibles :</strong><br><br>";
            foreach ($promotions as $promotion) {
                $libelle = htmlspecialchars($promotion['libelle'], ENT_QUOTES, 'UTF-8');
                $description = htmlspecialchars($promotion['description'] ?? '', ENT_QUOTES, 'UTF-8');
                $remise = $promotion['type_remise'] === 'Pourcentage' ? 
                    $promotion['valeur_remise'] . "%" : 
                    number_format((float) $promotion['valeur_remise'], 0, ',', ' ') . " FCFA";
                $reponse .= "🏷️ <strong>" . $libelle . "</strong><br>" . $description . "<br>Remise : <strong>" . $remise . "</strong><br><br>";
            }
        }
    } catch(PDOException $e) {
        $reponse = "🏷️ Désolé, je n'arrive pas à récupérer les promotions pour le moment.";
    }
}

// Commandes (connecté uniquement)
elseif (str_contains($question, 'commande') || str_contains($question, 'achat') || 
        str_contains($question, 'mes commandes')) {
    if (!$id_client) {
        $reponse = "🔐 Vous devez être connecté pour consulter vos commandes.<br><br><a href='connexion_client.php' style='color:#6d1626; font-weight:600;'>Se connecter</a> ou <a href='inscription.php' style='color:#6d1626; font-weight:600;'>Créer un compte</a>";
    } else {
        try {
            $stmt = $connexion->prepare("
                SELECT id_commande, date_commande, montant_total, statut, mode_livraison
                FROM commande WHERE id_client = ? ORDER BY date_commande DESC LIMIT 10
            ");
            $stmt->execute([$id_client]);
            $commandes = $stmt->fetchAll();
            if (!$commandes) {
                $reponse = "🛒 Vous n'avez encore aucune commande.";
            } else {
                $reponse = "<strong>🛒 Vos commandes :</strong><br><br>";
                foreach ($commandes as $commande) {
                    $reponse .= "📦 <strong>Commande #" . $commande['id_commande'] . "</strong><br>" .
                        "Montant : " . number_format($commande['montant_total'], 0, ',', ' ') . " FCFA<br>" .
                        "Statut : <strong>" . htmlspecialchars($commande['statut'], ENT_QUOTES, 'UTF-8') . "</strong><br><br>";
                }
                $reponse .= "<em>Voir toutes vos commandes dans votre espace client.</em>";
            }
        } catch(PDOException $e) {
            $reponse = "🛒 Désolé, je n'arrive pas à récupérer vos commandes pour le moment.";
        }
    }
}

// Livraison (connecté uniquement)
elseif (str_contains($question, 'livraison') || str_contains($question, 'livrer') || 
        str_contains($question, 'suivi')) {
    if (!$id_client) {
        $reponse = "🔐 Connectez-vous pour consulter vos livraisons.<br><br><a href='connexion_client.php' style='color:#6d1626; font-weight:600;'>Se connecter</a> ou <a href='inscription.php' style='color:#6d1626; font-weight:600;'>Créer un compte</a>";
    } else {
        try {
            $stmt = $connexion->prepare("
                SELECT l.id_livraison, l.adresse_livraison, l.statut, l.num_suivi, c.id_commande
                FROM livraison l INNER JOIN commande c ON c.id_commande = l.id_commande
                WHERE c.id_client = ? ORDER BY l.id_livraison DESC LIMIT 5
            ");
            $stmt->execute([$id_client]);
            $livraisons = $stmt->fetchAll();
            if (!$livraisons) {
                $reponse = "🚚 Aucune livraison trouvée.";
            } else {
                $reponse = "<strong>🚚 Vos livraisons :</strong><br><br>";
                foreach ($livraisons as $livraison) {
                    $reponse .= "📦 Commande #" . $livraison['id_commande'] . "<br>" .
                        "Statut : <strong>" . htmlspecialchars($livraison['statut'], ENT_QUOTES, 'UTF-8') . "</strong><br>" .
                        "Suivi : " . ($livraison['num_suivi'] ? htmlspecialchars($livraison['num_suivi'], ENT_QUOTES, 'UTF-8') : "Pas encore disponible") . "<br><br>";
                }
            }
        } catch(PDOException $e) {
            $reponse = "🚚 Désolé, je n'arrive pas à récupérer vos livraisons pour le moment.";
        }
    }
}

// Paiements (connecté uniquement)
elseif (str_contains($question, 'paiement') || str_contains($question, 'payer') || 
        str_contains($question, 'payement')) {
    if (!$id_client) {
        $reponse = "🔐 Connectez-vous pour consulter vos paiements.<br><br><a href='connexion_client.php' style='color:#6d1626; font-weight:600;'>Se connecter</a> ou <a href='inscription.php' style='color:#6d1626; font-weight:600;'>Créer un compte</a>";
    } else {
        try {
            $stmt = $connexion->prepare("
                SELECT p.id_paiement, p.date_paiement, p.mode_paiement, p.montant, p.statut, p.reference_transaction, p.id_commande
                FROM paiement p INNER JOIN commande c ON c.id_commande = p.id_commande
                WHERE c.id_client = ? ORDER BY p.date_paiement DESC LIMIT 5
            ");
            $stmt->execute([$id_client]);
            $paiements = $stmt->fetchAll();
            if (!$paiements) {
                $reponse = "💳 Aucun paiement enregistré pour votre compte.";
            } else {
                $reponse = "<strong>💳 Vos paiements :</strong><br><br>";
                foreach ($paiements as $paiement) {
                    $reponse .= "💳 Commande #" . $paiement['id_commande'] . "<br>" .
                        "Montant : " . number_format($paiement['montant'], 0, ',', ' ') . " FCFA<br>" .
                        "Statut : <strong>" . htmlspecialchars($paiement['statut'], ENT_QUOTES, 'UTF-8') . "</strong><br><br>";
                }
            }
        } catch(PDOException $e) {
            $reponse = "💳 Désolé, je n'arrive pas à récupérer vos paiements pour le moment.";
        }
    }
}

// Catégories
elseif (str_contains($question, 'categorie') || str_contains($question, 'categories') || 
        str_contains($question, 'type de vin')) {
    try {
        $stmt = $connexion->query("
            SELECT libelle, type_categorie FROM categorie WHERE statut = 'Actif' ORDER BY type_categorie, libelle
        ");
        $categories = $stmt->fetchAll();
        $reponse = "<strong>🍇 Catégories de vins :</strong><br><br>";
        foreach ($categories as $categorie) {
            $reponse .= "• <strong>" . htmlspecialchars($categorie['libelle'], ENT_QUOTES, 'UTF-8') . 
                "</strong> (" . htmlspecialchars($categorie['type_categorie'] ?? 'Vin', ENT_QUOTES, 'UTF-8') . ")<br>";
        }
    } catch(PDOException $e) {
        $reponse = "🍇 Désolé, je n'arrive pas à récupérer les catégories pour le moment.";
    }
}

// Question non comprise
else {
    $reponse = "🤖 Je n'ai pas bien compris votre demande.<br><br>Vous pouvez essayer :<br>" .
        "🍷 « Quels vins avez-vous ? »<br>" .
        "💰 « Quels sont les prix ? »<br>" .
        "📦 « Quel est le stock ? »<br>" .
        "🏷️ « Quelles sont les promotions ? »<br>" .
        "🛒 « Voir mes commandes »<br>" .
        "🚚 « Où est ma livraison ? »<br>" .
        "💳 « Voir mes paiements »";
}

// Enregistrer la réponse
enregistrerMessage($connexion, strip_tags($reponse), "Chatbot", $id_client, $id_chatbot);

// Réponse JSON
echo json_encode([
    "success" => true,
    "response" => $reponse
], JSON_UNESCAPED_UNICODE);

exit;