# Les Creations de Tiffany

Application web complète pour un salon de coiffure — prise de rendez-vous, boutique en ligne, portfolio et gestion administrative.

**Production** : [tiffany.garagepro.be](https://tiffany.garagepro.be)

---

## Stack Technique

| Couche | Technologie |
|--------|-------------|
| Frontend | React 19, Vite 6, CSS custom properties |
| Backend | Symfony 7.1 (PHP 8.2+), Architecture Hexagonale |
| Base de données | MySQL 8 |
| Auth | JWT (LexikJWTAuthenticationBundle) |
| Notifications | Web Push (VAPID ES256, AES-128-GCM) |
| Déploiement | cPanel Git Version Control |

## Fonctionnalites

### Client
- Prise de rendez-vous en ligne avec creneaux en temps reel
- Boutique produits avec panier, recherche et favoris
- Portfolio photos organise par albums
- Cartes cadeau (achat + envoi par email)
- Liste d'attente intelligente par service
- Notifications push (iOS, Android, Web)
- Espace client : historique RDV, commandes, programme fidelite
- Gestion des membres de la famille
- Mode sombre / clair

### Administration
- Dashboard avec statistiques et logs
- Gestion complète : services, produits, categories, portfolio
- Calendrier des rendez-vous (confirmation, completion, annulation, report)
- Gestion des commandes avec suivi de statut
- Envoi de notifications push (broadcast + ciblé)
- Gestion des témoignages clients
- Configuration SMTP et SMS
- Paramètres du site et horaires d'ouverture
- Mode maintenance

## Architecture

```
backend/
├── src/
│   ├── Domain/           # Entites, Ports (interfaces), Value Objects
│   ├── Application/      # Services metier (Mailer, Push, etc.)
│   ├── Infrastructure/   # Doctrine repositories, implementations
│   └── Presentation/     # Controllers API, Commands CLI
├── config/
└── public/

frontend/ (build -> assets/)
├── components/           # React components (lazy-loaded)
├── services/             # API clients (axios)
├── hooks/                # Custom hooks
└── styles/               # CSS avec variables + dark mode
```

### Architecture Hexagonale (Backend)

```
Domain (Entities + Ports)
    ↑
Application (Services)
    ↑
Infrastructure (Doctrine, SMTP, Push)
    ↑
Presentation (Controllers, Commands)
```

## API Endpoints

### Public
| Methode | Route | Description |
|---------|-------|-------------|
| GET | `/api/public/services` | Liste des services |
| GET | `/api/public/products` | Liste des produits |
| GET | `/api/public/portfolio` | Portfolio photos |
| GET | `/api/public/portfolio/albums` | Albums portfolio |
| GET | `/api/public/settings` | Paramètres du site |
| GET | `/api/public/schedule` | Horaires d'ouverture |
| GET | `/api/public/testimonials` | Témoignages |
| GET | `/api/public/product-categories` | Catégories produits |
| GET | `/api/public/appointments/booked-slots` | Créneaux occupés |
| GET | `/api/public/gift-cards/check` | Vérifier solde carte cadeau |
| POST | `/api/public/contact` | Formulaire de contact |

### Authentification
| Methode | Route | Description |
|---------|-------|-------------|
| POST | `/api/auth/login` | Connexion (retourne JWT) |
| POST | `/api/auth/register` | Inscription |
| GET | `/api/auth/profile` | Profil utilisateur |
| PUT | `/api/auth/profile` | Modifier profil |

### Client (authentifie)
| Methode | Route | Description |
|---------|-------|-------------|
| POST | `/api/appointments` | Prendre rendez-vous |
| GET | `/api/appointments` | Mes rendez-vous |
| PATCH | `/api/appointments/:id/cancel` | Annuler (24h min) |
| GET | `/api/appointments/loyalty` | Programme fidélité |
| POST | `/api/orders` | Passer commande |
| GET | `/api/orders` | Mes commandes |
| POST | `/api/gift-cards` | Acheter carte cadeau |
| GET | `/api/gift-cards` | Mes cartes cadeau |
| POST | `/api/waitlist` | Rejoindre liste d'attente |
| GET | `/api/waitlist` | Ma liste d'attente |
| DELETE | `/api/waitlist/:id` | Quitter liste d'attente |
| POST | `/api/push/subscribe` | S'abonner aux notifications |
| POST | `/api/push/unsubscribe` | Se désabonner |

### Admin
| Methode | Route | Description |
|---------|-------|-------------|
| CRUD | `/api/admin/services` | Gestion services |
| CRUD | `/api/admin/products` | Gestion produits |
| CRUD | `/api/admin/product-categories` | Gestion catégories |
| CRUD | `/api/admin/portfolio` | Gestion portfolio |
| CRUD | `/api/admin/portfolio/albums` | Gestion albums |
| CRUD | `/api/admin/testimonials` | Gestion témoignages |
| GET | `/api/admin/appointments` | Tous les RDV |
| PATCH | `/api/admin/appointments/:id/confirm` | Confirmer RDV |
| PATCH | `/api/admin/appointments/:id/complete` | Terminer RDV |
| PATCH | `/api/admin/appointments/:id/cancel` | Annuler RDV |
| PATCH | `/api/admin/appointments/:id/reschedule` | Reporter RDV |
| GET/PATCH | `/api/admin/orders` | Gestion commandes |
| PUT | `/api/admin/settings` | Modifier paramètres |
| PUT | `/api/admin/schedule` | Modifier horaires |
| POST | `/api/admin/push/send` | Envoyer notification push |
| POST | `/api/admin/push/generate-vapid` | Générer clés VAPID |
| GET | `/api/admin/stats` | Statistiques |
| GET | `/api/admin/logs` | Logs admin |
| POST | `/api/admin/upload` | Upload image |

## Performance

- **Code splitting** : chargement lazy de tous les composants (204 KB initial, -70%)
- **Chunk admin séparé** : 429 KB chargé uniquement pour les admins
- **Skeleton loaders** : affichage instantané pendant le chargement
- **Images lazy-loaded** : `loading="lazy"` sur toutes les images
- **Service Worker** : cache offline + notifications push
- **SEO** : sitemap.xml + robots.txt

## Commandes CLI

```bash
# Relance clients inactifs (60 jours par défaut)
php bin/console app:reminders:noshow --days=60

# Rappels de rendez-vous du lendemain
php bin/console app:email:reminders
```

## Deploiement

Ce repository contient le **build de production** (frontend compilé + backend Symfony). Il est déployé via cPanel Git Version Control.

```bash
# Build frontend
cd frontend && npm run build    # Output -> dist_build/

# Push
cd dist_build && git add -A && git commit -m "Deploy" && git push origin main

# Sur le serveur
php bin/console cache:clear --env=prod
```

### Tables SQL requises

Les migrations Doctrine créent automatiquement : `users`, `services`, `appointments`, `products`, `product_categories`, `orders`, `order_items`, `portfolio_items`, `portfolio_albums`, `site_settings`, `family_members`, `admin_logs`, `push_subscriptions`, `gift_cards`, `waitlist`.

---

Developpe par **AgimCoding**
