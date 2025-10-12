import "./bootstrap.js";
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import "./styles/app.scss";

console.log("This log comes from assets/app.js - welcome to AssetMapper! 🎉");

// 📁 assets/app.js

// Démarre une fois que le DOM est prêt
// document.addEventListener("DOMContentLoaded", () => {
//     console.log("🌐 App.js chargé");

//     const pageTitle = document.title.toLowerCase();

//     if (pageTitle.includes("dashboard")) {
//         import("./js/dashboard.js").then((module) => {
//             console.log("📦 dashboard.js chargé");
//             // Tu peux appeler une fonction si exportée : module.initDashboard()
//         });
//     } else if (pageTitle.includes("commande")) {
//         import("./js/commande.js").then((module) => {
//             console.log("📦 commande.js chargé");
//             // module.initCommande(); si besoin
//         });
//     } else if (
//         pageTitle.includes("entrepôt") ||
//         pageTitle.includes("entrepot")
//     ) {
//         import("./js/entrepot.js").then((module) => {
//             console.log("📦 entrepot.js chargé");
//         });
//     } else if (
//         pageTitle.includes("collecte") ||
//         pageTitle.includes("livraison")
//     ) {
//         import("./js/collecte.js").then((module) => {
//             console.log("📦 collecte.js chargé");
//         });
//     }
// });
