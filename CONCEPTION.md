# ClaudetteMcp — Conception

Document de conception du plugin Dotclear **ClaudetteMcp**.
État au 11 novembre 2026 : étapes 1 à 4 codées et publiées (ApiServer 1.0).

## Objectif

Permettre à un assistant IA (Claude, ou tout autre client compatible) de travailler sur un blog Dotclear
sans passer par un navigateur : lire les billets, en préparer, leur attacher un média à la une, les programmer.

Le plugin expose pour cela un serveur **MCP** (Model Context Protocol) : une API qui annonce une liste
d'outils typés, que l'assistant appelle directement.

## Contraintes

* **Simple à installer** : un plugin Dotclear comme un autre, sans toucher à la configuration du serveur web (Apache, nginx).
* **Fonctionne partout** : blog à la racine, en sous-dossier, en sous-domaine, avec ou sans réécriture d'URL, en multiblog.
* **Jamais de mot de passe confié à l'assistant.**
* **Cœur et plugins officiels uniquement** : les outils ne s'appuient que sur le cœur de Dotclear et les plugins livrés avec (tags, pages, attachments, …).
* **Les permissions Dotclear font foi** : l'assistant ne peut rien faire de plus que l'utilisateur qui l'a autorisé.
  Attention, ce n'est pas automatique : voir « Permissions dans les outils ».
* Le blog doit être joignable publiquement en HTTPS : les appels partent des serveurs de l'éditeur de l'assistant, pas du poste de l'utilisateur.

## Architecture

Deux plugins :

| Plugin | Rôle |
|---|---|
| **ApiServer** (existant) | Transport : routage `api/<endpoint>`, limite d'appels, logs, permission par blog. À faire évoluer, voir plus bas. |
| **ClaudetteMcp** (nouveau) | Déclare l'endpoint `mcp` via le behavior `ApiServerAddEndpoint`, gère les clés d'accès, contient les outils. |

Les outils n'utilisent que les API du cœur (`App::blog()`, `App::media()`, …) sous l'identité de l'utilisateur
propriétaire de la clé.

### Permissions dans les outils

Les outils s'exécutent dans le contexte public (frontend) du blog. Dans ce contexte, le cœur ne limite pas
les contenus aux permissions de l'utilisateur :

* `getPosts()` n'applique pas la règle « ses propres billets » de l'admin ;
* un statut demandé s'**ajoute** aux billets publiés au lieu de filtrer, et n'est pas vérifié.

Chaque outil doit donc vérifier lui-même les permissions. Règle retenue pour les billets :
les billets publiés sont visibles de tous ; les autres statuts et les billets protégés par mot de passe
ne sont visibles que de leur auteur ou d'un administrateur de contenu.

## Authentification : une clé, pas d'OAuth

### Pourquoi pas OAuth en v1

Le parcours OAuth attendu par les clients MCP impose de publier un document de découverte à
`/.well-known/oauth-authorization-server`, **à la racine de l'hôte**. D'après la documentation de Claude et
des rapports de bug concordants (dont un sur WordPress en sous-dossier), le chemin de l'installation n'est pas pris en compte.

Conséquences pour Dotclear :

* blog en sous-dossier : impossible sans configurer le serveur web ;
* blog sans réécriture d'URL (`index.php?`) : `/.well-known/…` n'atteint jamais Dotclear, même à la racine ;
* l'admin, bien qu'unique sur un multiblog, est elle aussi en sous-dossier (`/admin/`).

Cela contredit la contrainte de simplicité. OAuth reste une **option future**, pour les blogs à la racine
d'un domaine avec réécriture d'URL.

### Le choix retenu

Le client se connecte sans OAuth, avec une clé secrète envoyée dans un en-tête de requête :

```
https://exemple.tld/api/mcp
https://exemple.tld/sous-dossier/index.php?api/mcp
X-Api-Key: <clé>
```

L'en-tête est la voie recommandée (depuis la version 0.2) : la clé n'apparaît plus dans les journaux d'accès du serveur web.
Le nom `X-Api-Key` n'est pas libre : les connecteurs de Claude n'envoient que des noms d'en-tête d'une liste approuvée
par Anthropic (`authorization`, `x-api-key`, `x-auth-token`, …), et refusent les autres à l'enregistrement.
`Authorization: Bearer <clé>` est aussi accepté par le plugin, mais n'est pas la voie recommandée : certaines
configurations (Apache avec PHP en CGI) ne transmettent pas cet en-tête à PHP sans réglage du serveur.

Pour les clients qui ne savent pas envoyer d'en-tête, une clé peut aussi être acceptée dans l'adresse
(`…/api/mcp/<clé>`), si l'option « en-tête uniquement » de la clé est décochée. Les clés créées en 0.1 restent
acceptées des deux façons.

* La clé se crée dans **l'admin**, sur une page du plugin (menu Blog). L'utilisateur y est authentifié par Dotclear
  (mot de passe, OTP, clé de sécurité) : l'assistant ne voit jamais d'identifiants.
* Pourquoi une page dédiée plutôt que les préférences utilisateur : l'adresse est affichée dans la réponse même
  à la création, sans redirection ni message stocké en base, donc la clé ne transite par aucun stockage.
* Une clé est liée à **un utilisateur** et à **un seul blog** (décision arrêtée : pas de clé multi-blogs). Sur un multiblog, tout se gère depuis la même admin, avec une clé et un connecteur par blog.
* Clé longue et aléatoire, **affichée une seule fois**, stockée **hachée**, avec un libellé et une date de création.
* **Révocable** d'un clic. Durée de vie optionnelle.
* L'utilisateur doit avoir la permission du plugin sur le blog (comme pour ApiServer).

### Compromis assumé

Une clé fixe est moins sûre qu'OAuth : il n'y a pas d'écran de consentement ni de renouvellement automatique,
et une clé placée dans l'adresse peut se retrouver dans les journaux du serveur web. Ce qui limite les dégâts :

* clé dans un en-tête par défaut ;

* révocation immédiate ;
* limite d'appels et logs d'ApiServer ;
* pas d'outil de suppression ;
* option par clé « lecture seule » ;
* HTTPS obligatoire.

### Stockage des clés : table `credential` du cœur

Les clés sont stockées dans la table `credential` (Dotclear 2.36+), via `App::credential()`, sans modifier sa structure.

| Champ | Usage par ClaudetteMcp |
|---|---|
| `credential_type` (varchar 32) | `claudettemcp` |
| `user_id` | l'utilisateur propriétaire de la clé |
| `blog_id` | le blog auquel la clé donne accès |
| `credential_value` (varchar 255) | l'empreinte SHA-256 de la clé (64 caractères), jamais la clé elle-même |
| `credential_dt` | la date de création |
| `credential_data` (chiffré par le cœur) | libellé, lecture seule, date d'expiration |

La structure convient :

* la contrainte d'unicité porte sur (type, utilisateur, blog, valeur) : plusieurs clés par utilisateur et par blog sont possibles ;
* retrouver l'utilisateur d'une clé se fait avec `getCredentials()` sur le type, le blog et l'empreinte ;
* les clés étrangères en cascade suppriment les clés quand l'utilisateur ou le blog est supprimé ;
* modifier le libellé ou une option d'une clé se fait par suppression puis réinsertion avec la même empreinte.

Limites :

* pas de date de dernière utilisation : l'API n'a pas de mise à jour, et écrire dans la table à chaque appel n'est pas souhaitable.
  Les logs d'ApiServer jouent ce rôle ;
* `delCredentials()` ne supprime que pour le blog courant ou en global : suffisant depuis l'admin du blog concerné.

Pas d'interférence avec les usages existants (vérifié sur le code de la 2.40) :

* le cœur utilise les types `webauthn`, celui de l'OTP et `oauth2_<fournisseur>`, et filtre toujours sur le type :
  aucune requête ne parcourt la table tous types confondus ;
* le cœur n'enregistre que des lignes globales (`blog_id` nul), ClaudetteMcp que des lignes liées à un blog ;
* les listes de l'admin (clés de sécurité, applications tierces) ne montrent donc pas les clés de ClaudetteMcp.

Précautions dans le code du plugin :

* toujours passer `credential_type` à `getCredentials()`, qui vaut `webauthn` par défaut ;
* toujours passer `blog_id`, sans quoi seules les lignes globales sont lues ;
* supprimer ses lignes à la désinstallation du plugin (**reste à faire** : le plugin Uninstaller n'a pas de nettoyeur pour cette table) ;
* l'export complet du plugin importExport n'inclut pas la table `credential` : après une migration, les clés sont à recréer.

## Évolutions d'ApiServer

À faire sans casser DotclearWatch, DotclearWatchServer ni daContribute.

1. **Corps JSON brut.** Un endpoint ne reçoit aujourd'hui que des champs `$_POST` en chaînes, déclarés dans `FIELDS`.
   MCP envoie du JSON-RPC en `application/json` : il faut un mode d'endpoint qui lit `php://input`.
2. **Réponse libre.** `sendContent()` impose l'enveloppe `{code, message, content, cache}`.
   MCP attend une réponse JSON-RPC nue, avec ses propres codes HTTP.
3. **Authentification déléguée.** Un endpoint doit pouvoir identifier l'utilisateur autrement que par le jeton JWT
   de l'en-tête `Authorization` (ici : par la clé de l'URL), en surchargeant `checkUser()`.
4. Cache désactivé pour l'endpoint (constante `CACHE` existante).

Fait sur la branche `raw-endpoint` : `ApiServer::getBody()`, `getJson()`, `ApiServerEndpoint::sendRaw()` et `setVerifiedUser()`.
Les logs d'ApiServer n'enregistrent que le nom de l'endpoint : la clé n'y apparaît pas.

## Protocole

* Transport **Streamable HTTP**, en version minimale : une requête POST JSON-RPC, une réponse JSON. Pas de flux SSE, donc du PHP classique suffit.
* Méthodes à gérer : `initialize`, `notifications/initialized`, `tools/list`, `tools/call`, `ping`.
* Sans état : pas de session côté serveur.
* Chaque outil déclare son schéma d'entrée et une annotation **lecture seule** ou **écriture**, pour que le client
  puisse demander confirmation avant d'écrire.

Versions du protocole gérées : `2025-03-26`, `2025-06-18` et `2025-11-25`. Une version inconnue dans l'en-tête
`MCP-Protocol-Version` reçoit un 400 avec la liste des versions gérées. Un GET reçoit un 405, une notification un 202.

Sans clé valide (absente, inconnue, expirée, envoyée par la mauvaise voie, ou utilisateur sans permission), l'appel est
anonyme : le serveur ne répond qu'à la poignée de main (`initialize`, `ping`), sans rien dire du blog, et renvoie un 401
pour tout le reste. C'est nécessaire parce que le client vérifie l'adresse du connecteur avant d'envoyer les en-têtes.

**À suivre** : la révision `2026-07-28` du protocole supprime la poignée de main `initialize` et impose de nouveaux
en-têtes (`Mcp-Method`, `Mcp-Name`). Le serveur est déjà sans état, mais cette révision n'est ni déclarée ni testée.

## Outils de la v1

| Outil | Type | Permission Dotclear |
|---|---|---|
| `get_blog` | lecture | API (ApiServer) |
| `list_posts`, `get_post` | lecture | API ; contentadmin ou auteur pour le non publié |
| `list_categories`, `list_tags` | lecture | API |
| `list_media` | lecture | API et media |

| `create_post` | écriture | usage |
| `update_post` | écriture | usage (ses billets) ou contentadmin |
| `set_post_status` (publier, programmer, mettre en attente) | écriture | publish |

Règles :

* `create_post` crée toujours en **non publié**. Publier ou programmer passe par `set_post_status`.
* **Pas de suppression** en v1.
* `set_post_status` refuse de programmer un billet dont la date n'est pas dans le futur, et demande dans sa description
  de n'être utilisé que sur demande explicite de l'utilisateur.
* Les pings ne sont jamais envoyés.
* Réponses courtes : listes paginées, contenu complet d'un billet uniquement via `get_post`.
* Les types d'entrées autres que `post` (pages, …) viendront plus tard.
* `update_post` ne change jamais le statut, mais une modification d'un billet déjà publié est visible aussitôt.
* Formats en écriture : `xhtml` et `wiki` seulement. Les formateurs ne sont enregistrés par les éditeurs que dans l'admin ;
  le HTML n'en a pas besoin et le wiki est enregistré par l'outil comme le fait dcLegacyEditor. Un billet dans un autre
  format (markdown, …) est refusé en écriture.
* Les mots-clés sont écrits directement avec `App::meta()`, le plugin tags ne les gérant que depuis le formulaire de l'admin.
* Les dates sont dans le fuseau de l'utilisateur, comme dans l'admin.

Le média à la une n'est ni dans le cœur ni dans un plugin officiel : il est hors périmètre.
`list_tags` et les mots-clés des billets dépendent du plugin officiel tags, et ne sont proposés que s'il est actif.

## Étapes

1. **ApiServer** : corps JSON, réponse libre, authentification déléguée.
2. **ClaudetteMcp, lecture seule** : endpoint `mcp`, gestion des clés dans l'admin, outils de lecture.
   Test avec un client MCP de développement, puis comme connecteur dans Claude.
3. **Écriture** : `create_post`, `update_post`.
4. **Publication** : `set_post_status`.
5. Plus tard : OAuth optionnel.

## Feuille de route (après Dotclear 2.40)

Règle : une clé ne donne jamais plus que les permissions Dotclear de son utilisateur, sans exception ni outil réservé.
Elle peut seulement donner moins (lecture seule aujourd'hui, familles d'outils plus tard).

La plupart des utilisateurs créeront la clé sur leur propre compte, souvent administrateur ou super administrateur,
plutôt que sur un utilisateur dédié. La liste des outils est donc le vrai garde-fou : pas de suppression de billet,
pas d'accès aux paramètres du blog, aux utilisateurs, aux permissions, aux plugins, aux thèmes ni aux clés.
Le README doit continuer à conseiller un utilisateur dédié.

Outils prévus, par ordre d'utilité :

1. Média à la une et annexes d'un billet (lier et délier un média existant).
2. Envoi d'un média.
3. Pages (plugin officiel `pages`).
4. Commentaires : lister, lire, changer le statut. Pas de réponse au nom de l'utilisateur.
5. Création de catégorie.
6. Notes personnelles du billet (`post_notes`), en lecture et en écriture.

Ensuite : choix des familles d'outils (billets, pages, médias, commentaires) à la création de la clé.

## Points à vérifier

Vérifié le 11 novembre 2026 sur dotclear.watch : Claude se connecte sans OAuth à une adresse `https://…/api/mcp/<clé>`
et utilise les six outils de lecture.

Vérifié le même jour sur un blog sans réécriture d'URL : l'adresse `https://…/index.php?api/mcp/<clé>` est acceptée
telle quelle par Claude, lecture, écriture et publication comprises.

Vérifié aussi avec une clé « en-tête uniquement » : adresse `…/index.php?api/mcp` sans clé et en-tête `x-api-key`
saisi dans les réglages du connecteur de Claude, lecture et écriture comprises.

Piège rencontré : un plugin qui intercepte toutes les pages publiques (ex : disclaimer) renvoie sa page HTML à la place
de la réponse, et le client refuse alors le connecteur avec un message trompeur sur l'authentification.

## Références

* [Model Context Protocol](https://modelcontextprotocol.io)
* [Claude : créer un connecteur](https://claude.com/docs/connectors/building)
* [Claude : authentification des connecteurs](https://claude.com/docs/connectors/building/authentication)
* [ApiServer](https://github.com/JcDenis/ApiServer)
