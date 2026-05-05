# 🌐 MinotOr Front Web

Application web Symfony 7 + Twig pour la plateforme MinotOr.  
Authentification JWT avec gestion des rôles, dashboards dédiés par rôle, déployée avec Docker et CI/CD GitLab.

---

## 🚀 Stack technique

| Technologie | Rôle |
|---|---|
| PHP 8.2 + Symfony 7.3 | Framework backend |
| Twig | Templates HTML |
| Doctrine ORM | Accès base de données |
| MySQL | Stockage des données |
| JWT (Lexik) | Authentification |
| PHPUnit | Tests unitaires et fonctionnels |
| Docker | Conteneurisation |
| GitLab CI/CD | Pipeline d'intégration continue |
| Webpack Encore | Assets JS/CSS |

---

## 🔗 Projets liés

- [MinotOr-API](https://github.com/Dravix89/MinotOr-API) — API REST PHP Symfony + JWT
- [Mobile_MinotOr](https://github.com/Dravix89/Mobile_MinotOr) — App mobile livreur React Native
- [Desktop_MinotOr](https://github.com/Dravix89/Desktop_MinotOr) — App desktop JavaFX admin

---

## 👥 Rôles et accès

| Rôle | Accès |
|---|---|
| Public (sans compte) | Pages publiques |
| Client | Dashboard client, suivi commandes, QR code |
| Approvisionneur | Gestion des approvisionnements |
| Commercial | Gestion commerciale |
| Maintenance | Suivi maintenance |
| Admin | App Desktop (JavaFX) |

---

## 📦 Installation avec Docker

```bash
git clone https://github.com/Dravix89/MinotOr_Front_Web.git
cd MinotOr_Front_Web
docker-compose up -d
composer install
php bin/console doctrine:migrations:migrate
```

---

## 🧪 Tests

```bash
php bin/phpunit
```

---

## 🔄 CI/CD GitLab

Pipeline GitLab avec `.gitlab-ci.yml` — installation des dépendances, tests PHPUnit, déploiement automatique sur merge.

---

## 🗂️ Structure

```
MinotOr_Front_Web/
├── src/
│   ├── Controller/
│   │   ├── PublicController.php
│   │   ├── ClientController.php
│   │   ├── ApprovisioneurController.php
│   │   ├── CommercialController.php
│   │   ├── MaintenanceController.php
│   │   ├── FactureController.php
│   │   └── SecurityController.php
│   ├── Entity/
│   └── Repository/
├── templates/
│   ├── public/
│   ├── client/
│   ├── approvisionneur/
│   ├── commercial/
│   ├── maintenance/
│   ├── security/
│   └── base.html.twig
├── tests/
├── docker-compose.yml
└── .gitlab-ci.yml
```

---

## 👤 Auteur

**DavidR** — [@Dravix89](https://github.com/Dravix89)
