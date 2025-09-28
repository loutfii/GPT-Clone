# CloneGPT (Laravel 12 + Vue 3)

Mini-clone de ChatGPT : authentification sécurisée (e-mail + 2FA), sélection dynamique de modèles via OpenRouter, multi-conversations persistées, streaming SSE, et instructions personnalisées par utilisateur.
Stack : PHP 8.2, Laravel 12 + Jetstream/Fortify/Sanctum, Vue 3 (Composition API) + Inertia + Tailwind, SQLite, Pest.

Captures & schémas dans le pdf.

## Fonctionnalités

Auth complète : Register/Login, vérification e-mail, 2FA TOTP (+ codes de secours).

Chat : envoi classique + streaming (SSE), historique et titres auto.

Multi-conversations (CRUD côté user) avec cascade des messages.

Modèles IA dynamiques (OpenRouter) avec fallback si l'API est indisponible.

Instructions personnalisées (tone, style, context, custom_system).

Front Vue 3 – Composition API uniquement (critère éliminatoire respecté).

Tests Pest (auth, 2FA, e-mail, chat, stream, settings).

UI Tailwind, Inertia, Vite.

## Prérequis

PHP 8.2+, Composer

Node 18+ (ou 20+), pnpm/npm/yarn

SQLite (inclus avec PHP)

Un compte Gmail avec 2FA activée pour les mails (ou utiliser MAIL_MAILER=log en dev)

## Installation rapide

```bash
# 1) cloner
git clone https://github.com/monLienGitHub/gpt-clone.git
cd gpt-clone

# 2) dépendances
composer install
npm install

# 3) fichier d'env
cp .env.example .env
php artisan key:generate

# 4) base SQLite
mkdir -p database
type NUL > database/database.sqlite   # Windows (PowerShell)
# touch database/database.sqlite      # macOS/Linux

# 5) migrations & liens
php artisan migrate
php artisan storage:link  # (optionnel)

# 6) démarrer
npm run dev
php artisan serve
```

Par défaut, la page d'accueil redirige vers /login. Une fois connecté et email vérifié, on arrive sur /chat.

## Configuration mail (Gmail + App Password)

Pour que la vérification d'e-mail et la 2FA envoient de vrais mails :

Active la vérification en deux étapes sur ton compte Google.


```

En dev "offline", tu peux aussi mettre MAIL_MAILER=log pour écrire les mails dans storage/logs/laravel.log.

## OpenRouter (modèles IA)

Créer une clé sur OpenRouter, puis :

```env
OPENROUTER_API_KEY=sk-or-...
OPENROUTER_BASE=https://openrouter.ai/api/v1
```

Le sélecteur de modèles se remplit via OpenRouterClient::models(). En cas de panne API, on a un fallback local.

## Routes principales

GET / → redirige vers login (si invité) ou chat (si authentifié)

GET /chat → page principale

POST /chat/send → envoi non-stream

POST /chat/stream → SSE (flux token-par-token)

GET /chat/{id} → charger une conversation

PATCH /chat/{id}/rename → renommer

DELETE /chat/{id} → supprimer (messages cascade)

GET /settings, POST /settings → préférences IA

Toutes ces routes sont sous middleware auth + verified.

## Tests

Lancer la suite :

```bash
php artisan test
# ou
./vendor/bin/pest
```

Environnement de test (.env.testing) :

base SQLite dédiée

mailer array (pas d'envoi réel)

cache array

Couverture : auth/register/email-verify/2FA/profil/chat/stream/settings.

Les tests Jetstream/Fortify générés par le scaffolding sont conservés. Nos tests custom (Pest) s'ajoutent par-dessus.

## Base de données

SQLite (fichier database/database.sqlite) pour dev et test.

Tables domaine :

users (Jetstream/Fortify, 2FA & email_verified_at)

conversations (FK user_id, on delete cascade)

messages (FK conversation_id, on delete cascade, role ∈ {system,user,assistant})

user_settings (FK user_id unique)

Tables techniques (framework) : sessions, personal_access_tokens, password_reset_tokens, jobs, job_batches, failed_jobs, cache, etc.

Schémas UML (à placer dans /docs) :

docs/UML_BDD.png – entités/relations + cardinalités 1..1 / 0..*

docs/UML_Classes.png – contrôleurs, service, modèles

## Sécurité

Vérification d'e-mail obligatoire (MustVerifyEmail).

2FA TOTP (Fortify) + codes de récupération.

CSRF inclus (y compris pour le SSE via X-CSRF-TOKEN + credentials: 'same-origin').

Eloquent → PDO requêtes préparées par défaut (protection injection SQL).

Scoping strict par user_id sur les conversations/messages.

## Dépannage rapide

SMTP 535 (auth échouée) → vérifier que tu utilises un App Password Gmail, et MAIL_ENCRYPTION=tls + port 587.

SSE 419 (CSRF) → vérifier la balise <meta name="csrf-token" ...> et que le front envoie bien X-CSRF-TOKEN + credentials: 'same-origin'.

Sessions qui sautent → utiliser SESSION_DRIVER=database + php artisan session:table.

Modèles OpenRouter vides → vérifier OPENROUTER_API_KEY; sinon fallback local.

## Structure (extrait)

```
app/
  Http/Controllers/ChatController.php
  Http/Controllers/UserSettingsController.php
  Models/{Conversation,Message,UserSetting}.php
  Services/OpenRouterClient.php
config/{jetstream,fortify,services}.php
database/migrations/*_create_{conversations,messages,user_settings}_table.php
resources/js/Pages/{Chat.vue,Settings.vue}
resources/js/Layouts/AppLayout.vue
tests/Feature/*  (Pest)
```

## Auteur

Ghazal Loutfi Adonis
