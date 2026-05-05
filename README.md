# 🖥️ Desktop MinotOr

Application desktop JavaFX destinée aux **administrateurs** de la plateforme MinotOr.  
Connectée à l'API REST Symfony (JWT + MySQL) et à une base MongoDB pour les statistiques.

---

## 🚀 Stack technique

| Technologie | Rôle |
|---|---|
| Java + JavaFX | Interface desktop |
| API REST Symfony | Authentification JWT |
| MySQL | Stockage des utilisateurs et rôles |
| MongoDB | Stockage des stats de navigation |
| Maven | Gestion des dépendances |

---

## 🔗 Projets liés

- [MinotOr-API](https://github.com/Dravix89/MinotOr-API) — API REST PHP Symfony + JWT
- [Mobile_MinotOr](https://github.com/Dravix89/Mobile_MinotOr) — App mobile livreur

---

## 📲 Fonctionnalités

### 🔐 Authentification
- Login admin avec JWT via l'API Symfony
- Vérification du rôle admin en MySQL

### 📊 Dashboard statistiques
- Visualisation des pages du site web MinotOr visitées par les utilisateurs
- Dates et heures de chaque visite
- Données collectées et stockées dans MongoDB (volume élevé)

---

## 📦 Installation

1. Cloner le projet
```bash
git clone https://github.com/Dravix89/Desktop_MinotOr.git
```

2. Ouvrir dans **IntelliJ IDEA**

3. Lancer le backend : [MinotOr-API](https://github.com/Dravix89/MinotOr-API)
```bash
php -S 0.0.0.0:8000 -t public
```

4. Lancer l'application via IntelliJ (`Main.java`)

---

## 🗂️ Structure

```
Desktop_MinotOr/
├── src/main/java/com/minotor/desktop_minotor/
│   ├── controller/
│   │   ├── LoginController.java
│   │   └── TrackingController.java
│   ├── model/
│   │   ├── User.java
│   │   └── PageVisit.java
│   ├── service/
│   │   ├── ApiService.java
│   │   ├── AuthService.java
│   │   ├── MongoDBConnector.java
│   │   └── TrackingService.java
│   └── utils/
├── src/main/resources/   # FXML + CSS
└── pom.xml
```

---

## 👤 Auteur

**DavidR** — [@Dravix89](https://github.com/Dravix89)
