# ClaudetteMcp — Conception

Document de conception du plugin Dotclear **ClaudetteMcp**.
État : brouillon du 11 novembre 2026, rien n'est encore codé.

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
* Le blog doit être joignable publiquement en HTTPS : les appels partent des serveurs de l'éditeur de l'assistant, pas du poste de l'utilisateur.

## Architecture

Deux plugins :

| Plugin | Rôle |
|---|---|
| **ApiServer** (existant) | Transport : routage `api/<endpoint>`, limite d'appels, logs, permission par blog. À faire évoluer, voir plus bas. |
| **ClaudetteMcp** (nouveau) | Déclare l'endpoint `mcp` via le behavior `ApiServerAddEndpoint`, gère les clés d'accès, contient les outils. |

Les outils n'utilisent que les API du cœur (`App::blog()`, `App::media()`, …) sous l'identité de l'utilisateur
propriétaire de la clé.

## Authentification : une clé dans l'adresse, pas d'OAuth

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

Le client se connecte sans OAuth, à une adresse qui contient une clé secrète :

```
https://exemple.tld/api/mcp/<clé>
https://exemple.tld/sous-dossier/index.php?api/mcp/<clé>
```

* La clé se crée dans **l'admin**, dans les préférences utilisateur. L'utilisateur y est authentifié par Dotclear
  (mot de passe, OTP, clé de sécurité) : l'assistant ne voit jamais d'identifiants.
* Une clé est liée à **un utilisateur** et à **un seul blog** (décision arrêtée : pas de clé multi-blogs). Sur un multiblog, tout se gère depuis la même admin, avec une clé et un connecteur par blog.
* Clé longue et aléatoire, **affichée une seule fois**, stockée **hachée**, avec un libellé et une date de création.
* **Révocable** d'un clic. Durée de vie optionnelle.
* L'utilisateur doit avoir la permission du plugin sur le blog (comme pour ApiServer).

### Compromis assumé

Une clé dans une URL est moins sûre qu'OAuth : elle peut se retrouver dans les journaux du serveur web
et il n'y a pas d'écran de consentement. Ce qui limite les dégâts :

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
* supprimer ses lignes à la désinstallation du plugin ;
* l'export complet du plugin importExport n'inclut pas la table `credential` : après une migration, les clés sont à recréer.

## Évolutions d'ApiServer

À faire sans casser DotclearWatch, DotclearWatchServer ni daContribute.

1. **Corps JSON brut.** Un endpoint ne reçoit aujourd'hui que des champs `$_POST` en chaînes, déclarés dans `FIELDS`.
   MCP envoie du JSON-RPC en `application/json` : il faut un mode d'endpoint qui lit `php://input`.
2. **Réponse libre.** `sendContent()` impose l'enveloppe `{code, message, content, cache}`.
   MCP attend une réponse JSON-RPC nue, avec ses propres codes HTTP.
3. **Authentification déléguée.** Un endpoint doit pouvoir identifier l'utilisateur autrement que par le jeton JWT
   de l'en-tête `Authorization` (ici : par la clé de l'URL), en surchargeant `checkUser()`.
4. **Ne pas journaliser la clé.** Les logs d'ApiServer ne doivent enregistrer que l'endpoint, pas ses arguments.
5. Cache désactivé pour l'endpoint (constante `CACHE` existante).

## Protocole

* Transport **Streamable HTTP**, en version minimale : une requête POST JSON-RPC, une réponse JSON. Pas de flux SSE, donc du PHP classique suffit.
* Méthodes à gérer : `initialize`, `notifications/initialized`, `tools/list`, `tools/call`, `ping`.
* Sans état : pas de session côté serveur.
* Chaque outil déclare son schéma d'entrée et une annotation **lecture seule** ou **écriture**, pour que le client
  puisse demander confirmation avant d'écrire.

Les détails (noms exacts, en-têtes, version du protocole) sont à vérifier dans la spécification MCP au moment de coder.

## Outils de la v1

| Outil | Type | Permission Dotclear |
|---|---|---|
| `get_blog` | lecture | usage |
| `list_posts`, `get_post` | lecture | usage |
| `list_categories`, `list_tags` | lecture | usage |
| `list_media` | lecture | media |
| `create_post` | écriture | usage |
| `update_post` | écriture | usage (ses billets) ou contentadmin |
| `set_post_status` (publier, programmer, mettre en attente) | écriture | publish |

Règles :

* `create_post` crée toujours en **non publié**. Publier ou programmer passe par `set_post_status`.
* **Pas de suppression** en v1.
* Les pings ne sont jamais envoyés.
* Réponses courtes : listes paginées, contenu complet d'un billet uniquement via `get_post`.
* Les types d'entrées autres que `post` (pages, …) viendront plus tard.

Le média à la une n'est ni dans le cœur ni dans un plugin officiel : il est hors périmètre.
`list_tags` et les mots-clés des billets dépendent du plugin officiel tags, et ne sont proposés que s'il est actif.

## Étapes

1. **ApiServer** : corps JSON, réponse libre, authentification déléguée.
2. **ClaudetteMcp, lecture seule** : endpoint `mcp`, gestion des clés dans l'admin, outils de lecture.
   Test avec un client MCP de développement, puis comme connecteur dans Claude.
3. **Écriture** : `create_post`, `update_post`.
4. **Publication** : `set_post_status`.
5. Plus tard : pages, commentaires, envoi de médias, OAuth optionnel.

## Points à vérifier

* Qu'un client accepte une adresse de connecteur avec chaîne de requête (`index.php?api/mcp/<clé>`).
* Le comportement exact d'un client face à un serveur sans OAuth (pas de 401 pendant la découverte).

## Références

* [Model Context Protocol](https://modelcontextprotocol.io)
* [Claude : créer un connecteur](https://claude.com/docs/connectors/building)
* [Claude : authentification des connecteurs](https://claude.com/docs/connectors/building/authentication)
* [ApiServer](https://github.com/JcDenis/ApiServer)
