# Val-Dieu Headless CMS

Craft lives in this `cms/` folder and is only responsible for content editing and API output. The public website remains the Eleventy/Nunjucks/SCSS site in the repo root.

## What It Provides

- Craft control panel at `/admin`.
- `Homepage`, `Discover`, `Community`, and `Support` Single entries for the editable repeated content.
- A `Content images` asset volume for images uploaded from those entries.
- JSON endpoints at `/api/homepage.json`, `/api/discover.json`, `/api/community.json`, and `/api/support.json`.
- Eleventy reads those endpoints during `npm run build` through the files in `src/_data`.
- Multilingual output: `/` is English, with `/fr/`, `/de/`, and `/nl/` variants for every page.
- Craft content is edited in French only. Dynamic entry content is translated during the Eleventy build.
- If Craft is unavailable, Eleventy keeps using local fallback content so builds do not break.

## Local Setup

1. Create a MySQL or PostgreSQL database for Craft.
2. Fill in `cms/.env` with the database values.
3. Install and initialize Craft:

```bash
cd cms
composer install
php craft setup
php craft plugin/install element-api
php craft migrate/up --interactive=0
php craft project-config/apply
php craft serve --port=8080
```

4. Open `http://localhost:8080/admin`.
5. Edit the Craft Singles:
   - `Homepage -> Homepage gallery`: upload/edit the 5 gallery images.
   - `Discover -> Discover timeline`: add, remove, or edit timeline dates, texts, and images.
   - `Community -> Agenda` and `Community news`
   - `Community -> Community news`: add, remove, or edit news photos, dates, titles, and texts.
   - `Support -> Support projects`: add, remove, or edit project titles, images, and texts.
6. In the repo root, create `.env` from `.env.example`:

```bash
CRAFT_API_URL=http://localhost:8080/api/homepage.json
```

7. Rebuild the static site:

```bash
npm run build
```

The homepage gallery, discover timeline, community agenda/news, and support projects should now come from Craft.

## Translations

- Static template text is handled with Craft-style PHP translation files in `cms/translations/{locale}/site.php`.
- Current static translation files are `cms/translations/en/site.php`, `cms/translations/fr/site.php`, and `cms/translations/nl/site.php`. German falls back to English until `cms/translations/de/site.php` exists.
- Craft entries are requested once in French. During `npm run build`, editable entry fields such as `title`, `text`, `alt`, `day`, and `subtitle` are translated to EN/NL/DE with DeepL.
- Translations are cached in `.cache/deepl-translations.json`, keyed by the source text hash, so unchanged text is not translated again.
- Build-time dynamic translation requires:

```bash
DEEPL_API_KEY=your-key
DEEPL_API_HOST=https://api-free.deepl.com
```

If `DEEPL_API_KEY` is missing, the build still succeeds and dynamic non-French pages use the French source content.

## OVH Shape

Recommended setup:

- Public website: static Eleventy output from `dist`.
- CMS: Craft hosted separately, ideally `cms.your-domain.be`, with web root set to `cms/web`.
- Build environment: set `CRAFT_API_URL=https://cms.your-domain.be/api/homepage.json`.
  Eleventy uses that URL to derive the other Craft API endpoints on the same CMS host.

Automatic rebuilds are handled by the versioned `github-dispatch` Craft module in `cms/modules`. When a non-draft Entry is saved, deleted, or restored, it queues a GitHub `repository_dispatch` event. This avoids storing the integration in Craft's database, where a database restore could silently remove it.

Production must define the following values in `cms/.env`:

```dotenv
GITHUB_DISPATCH_REPOSITORY=crokstudio/val-dieu
GITHUB_DISPATCH_TOKEN=github_pat_...
```

Use a fine-grained GitHub token restricted to this repository with only `Contents: write`. The token is read only while the queued job runs; it is never serialized in the queue payload or included in application logs. Automatic rebuilds do not use Craft's legacy Webhooks plug-in.

This repo includes `.github/workflows/rebuild-from-craft.yml` for that flow. Add these GitHub secrets before enabling OVH deployment:

- `CRAFT_API_URL`: public CMS endpoint, for example `https://cms.your-domain.be/api/homepage.json`.
- `DEEPL_API_KEY`: DeepL API key used to translate dynamic Craft entry content during rebuilds.
- `OVH_HOST`: OVH SSH host.
- `OVH_USER`: OVH SSH user.
- `OVH_DEPLOY_KEY`: private SSH key allowed to deploy to the OVH target.
- `OVH_TARGET_DIR`: directory where the static website should be deployed.

Every successful build on `main` deploys to OVH. The same workflow can also be started manually from GitHub Actions.

GitHub Actions sets `CRAFT_REQUIRE_API=true`, so an unavailable or invalid Craft API stops the deployment instead of publishing fallback content. OVH paths are validated against one-time root markers before every sync, all root dotfiles and `cms/` are protected, and recovery data for the ten latest deployments is kept outside the webroot. The exact deployed revision is always verified over SSH. Set the optional `OVH_SITE_URL` repository variable to an HTTPS public URL to add a public verification after each run.

Keep `CRAFT_ALLOW_ADMIN_CHANGES=false` in production once the content model is deployed. The client should edit entries and assets, while structural CMS changes stay in code/migrations.
