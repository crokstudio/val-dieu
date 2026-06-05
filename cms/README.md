# Val-Dieu Headless CMS

Craft lives in this `cms/` folder and is only responsible for content editing and API output. The public website remains the Eleventy/Nunjucks/SCSS site in the repo root.

## What It Provides

- Craft control panel at `/admin`.
- A `Homepage` Single entry with one editable field: `Intro text`.
- A JSON endpoint at `/api/homepage.json`.
- Eleventy reads that endpoint during `npm run build` through `src/_data/homepage.js`.
- If Craft is unavailable, Eleventy keeps using the hard-coded fallback lorem text so builds do not break.

## Local Setup

1. Create a MySQL or PostgreSQL database for Craft.
2. Fill in `cms/.env` with the database values.
3. Install and initialize Craft:

```bash
cd cms
composer install
php craft setup
php craft plugin/install element-api
php craft plugin/install webhooks
php craft migrate/up --interactive=0
php craft serve --port=8080
```

4. Open `http://localhost:8080/admin`.
5. Edit `Entries -> Homepage -> Intro text`.
6. In the repo root, create `.env` from `.env.example`:

```bash
CRAFT_API_URL=http://localhost:8080/api/homepage.json
```

7. Rebuild the static site:

```bash
npm run build
```

The homepage intro paragraph in `dist/index.html` should now come from Craft.

## OVH Shape

Recommended setup:

- Public website: static Eleventy output from `dist`.
- CMS: Craft hosted separately, ideally `cms.your-domain.be`, with web root set to `cms/web`.
- Build environment: set `CRAFT_API_URL=https://cms.your-domain.be/api/homepage.json`.

For automatic rebuilds, install the Webhooks plugin, then configure a webhook in Craft that fires when entries are saved. Point it to a GitHub Actions `repository_dispatch` or `workflow_dispatch` URL. GitHub Actions can then run `npm ci`, `npm run build`, and deploy `dist` to OVH.

This repo includes `.github/workflows/rebuild-from-craft.yml` for that flow. Add these GitHub secrets before enabling OVH deployment:

- `CRAFT_API_URL`: public CMS endpoint, for example `https://cms.your-domain.be/api/homepage.json`.
- `OVH_HOST`: OVH SSH host.
- `OVH_USER`: OVH SSH user.
- `OVH_DEPLOY_KEY`: private SSH key allowed to deploy to the OVH target.
- `OVH_TARGET_DIR`: directory where the static website should be deployed.

Then set the GitHub repository variable `ENABLE_OVH_DEPLOY=true`. Until that variable exists, the workflow only builds and uploads `dist` as an artifact.

Keep `CRAFT_ALLOW_ADMIN_CHANGES=false` in production once the content model is deployed. The client should edit entries and assets, while structural CMS changes stay in code/migrations.
