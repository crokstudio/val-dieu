# Migration vers abbaye-du-val-dieu.be

Ce document prépare la migration sans modifier les DNS publics. Le site public
historique doit rester en ligne jusqu'à la fin des contrôles de certificat.

## État de référence vérifié le 1er septembre 2026

- Domaine canonique prévu : `https://www.abbaye-du-val-dieu.be`.
- L'ancien site répond encore sur `13.81.212.217`.
- Le nouvel hébergement OVH répond sur `5.135.23.164` (cluster 100) et son
  répertoire multisite doit être `www`.
- Les serveurs DNS actifs sont `dns16.ovh.net` et `ns16.ovh.net`.
- Les entrées MX et TXT servent notamment Microsoft 365 et ne doivent jamais
  être remplacées ou supprimées pendant la migration.
- Aucun certificat couvrant le domaine final n'est encore actif sur le nouvel
  hébergement.
- Les deux noms ont été demandés dans le multisite avec le dossier `www`, sans
  modification DNS. OVH les considère toutefois comme temporaires tant que les
  enregistrements A et TXT ne sont pas conformes et peut annuler l'ajout ; il
  faudra le relancer le jour de la bascule s'il a expiré.
- La zone DNS active n'est pas éditable depuis le compte OVH actuellement
  ouvert. Il faut récupérer son contact de gestion avant toute bascule. Ne pas
  commander une zone vierge pour contourner ce blocage.
- Le premier lancement du workflow reste volontairement en mode préflight tant
  que `ops/deployment-enabled` n'existe pas : il construit le site et crée les
  sauvegardes privées vérifiées, mais ne modifie aucun fichier publié.

## Préparation réalisée dans le dépôt

- Les quatre langues ont une URL stable : `/fr/`, `/en/`, `/nl/` et `/de/`.
  La racine redirige définitivement vers `/fr/`, comme l'ancien site.
- Les balises canonical et hreflang, le sitemap XML et le fichier robots sont
  générés avec le domaine final.
- Les 675 URLs actuellement publiées dans les sitemaps WordPress (pages,
  articles, événements, attachments et anciens types de contenu) sont couvertes
  par une page existante ou une redirection vers la section équivalente. Tous
  les anciens événements `/LANG/agenda/...` vont vers la page Communauté.
- Le nom temporaire OVH reçoit un en-tête `X-Robots-Tag: noindex, nofollow`.
- Le déploiement autorise explicitement `.htaccess` tout en continuant à
  protéger les autres fichiers cachés du compte.
- Le formulaire de réservation utilise PHPMailer et un SMTP authentifié ; Craft
  bascule vers le même SMTP dès que ses variables secrètes sont présentes. Sans
  secret SMTP complet, le formulaire conserve automatiquement son transport
  `mail()` et son destinataire actuellement en production, afin que la migration
  du domaine ne coupe pas les demandes de réservation.
- Avant chaque déploiement, le workflow sauvegarde et vérifie la base Craft, les
  uploads, `.env`, la configuration et le point d'entrée `/cms`, hors webroot.
  Le rsync exclut le nœud `/cms` lui-même et tout éventuel `/uploads` racine.
- Le préflight copie aussi cette sauvegarde hors OVH dans un artifact GitHub
  chiffré avec `ops/offsite-backup-cert.pem`. La clé privée reste uniquement sur
  le poste de migration, dans `.migration-private/`, ignoré par Git.

## Migration SMTP séparée, sans bloquer la bascule du domaine

1. Copier `ops/mail-secrets.example.php` vers `$HOME/.val-dieu-mail.php` sur
   l'hébergement et remplacer les valeurs factices.
2. Protéger le fichier avec le mode `600`.
3. Dans `$HOME/craft/.env`, définir
   `CRAFT_SECRETS_PATH=/chemin/absolu/.val-dieu-mail.php`.
4. Vérifier que le compte SMTP autorise l'adresse d'expéditeur configurée.
5. Envoyer un test depuis Craft puis une réservation de test contrôlée ; vérifier
   la réception interne, la confirmation visiteur et les dossiers indésirables.

Le fichier secret est situé hors du dossier web et ne doit jamais être ajouté à
Git. Si Microsoft 365 est retenu, vérifier que SMTP AUTH est autorisé pour la
boîte choisie ; sinon utiliser un fournisseur transactionnel authentifié. Ne pas
installer une configuration SMTP partielle : elle est rejetée explicitement.

## Préparation OVH sans bascule

1. Vérifier que `abbaye-du-val-dieu.be` et `www.abbaye-du-val-dieu.be` sont
   toujours présents dans le multisite, dossier racine `www`. Leur ajout
   temporaire a été demandé sans modifier les DNS.
2. Ajouter uniquement le TXT `ovhcontrol` demandé par OVH dans la zone active.
   Cette entrée prouve le contrôle du domaine et ne déplace ni le site ni les
   e-mails.
3. Pousser d'abord la révision sans `ops/deployment-enabled`. Vérifier que
   l'Action a terminé la sauvegarde de la base et des uploads sans déployer.
   Télécharger l'artifact chiffré, le déchiffrer localement et vérifier ses
   sommes SHA-256 avant de poursuivre.
4. Ajouter ensuite le fichier marqueur `ops/deployment-enabled`, pousser la
   révision de déploiement, puis tester le nom temporaire, toutes les langues,
   les redirections, les médias et le formulaire.
5. Au moins 24 à 48 heures avant la bascule, réduire le TTL des deux entrées A à
   300 secondes. Ne toucher à aucun MX, SPF, DKIM, DMARC ou TXT de validation.

## Bascule, à exécuter seulement le jour validé

1. Confirmer que l'ancien site et le nouvel hébergement sont tous les deux
   opérationnels et conserver l'ancien serveur au moins 72 heures.
2. Modifier seulement les deux entrées A de l'apex et de `www` :
   `13.81.212.217` vers `5.135.23.164`.
3. Ne pas ajouter d'AAAA au premier passage. L'IPv6
   `2001:41d0:301::100` pourra être ajoutée après validation séparée.
4. Dès que les deux noms pointent vers OVH, demander le certificat Let's
   Encrypt pour l'apex et `www`, puis attendre son état actif.
5. Tester HTTPS, les deux noms d'hôte, les quatre langues, le sitemap, le CMS,
   le formulaire et `deploy-version.txt` depuis plusieurs résolveurs.
6. La redirection conditionnelle HTTP/apex vers
   `https://www.abbaye-du-val-dieu.be` est déjà préparée en 302 et ne s'active
   que pour les deux noms finaux. Après validation, la promouvoir en 301 puis
   définir la variable GitHub Actions
   `OVH_SITE_URL=https://www.abbaye-du-val-dieu.be`.
7. Après stabilisation, remettre le TTL à 3600 secondes.

OVH exige que le domaine pointe vers l'hébergement pour émettre ou importer le
certificat. Une courte fenêtre d'erreur HTTPS reste donc possible avec le flux
Let's Encrypt standard ; garder l'ancien site actif évite en revanche un trou
DNS. Planifier la bascule à faible trafic et lancer immédiatement la demande de
certificat réduit cette fenêtre.

## Retour arrière

Si le nouveau site ou le certificat échoue, remettre les deux entrées A sur
`13.81.212.217`. Avec un TTL de 300 secondes, la majorité des résolveurs
reviennent rapidement vers l'ancien site. Ne supprimer ni le multisite préparé,
ni les sauvegardes de déploiement avant la fin de la période de surveillance.

## Contrôles utiles

Avant la bascule, une fois le domaine déclaré dans le multisite :

```sh
curl --resolve www.abbaye-du-val-dieu.be:80:5.135.23.164 \
  http://www.abbaye-du-val-dieu.be/fr/
curl --resolve abbaye-du-val-dieu.be:80:5.135.23.164 \
  http://abbaye-du-val-dieu.be/fr/
```

Après émission du certificat, refaire les contrôles en HTTPS sans ignorer les
erreurs TLS et valider les redirections avec `curl -I`.
