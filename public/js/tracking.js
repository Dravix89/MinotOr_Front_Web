// public/js/tracking.js

document.addEventListener("DOMContentLoaded", () => {
    const skipPages = ["/client/login", "/client/tracking"];
    if (skipPages.includes(window.location.pathname)) return;

    alert("tracking.js chargé et exécuté"); // AFFICHAGE VISIBLE DIRECT QUAND LA PAGE CHARGE
    console.log("tracking.js chargé et exécuté");

    const pageName = window.location.pathname;
    const visitDate = new Date().toISOString();

    const token = localStorage.getItem("jwt_token");

    if (!token) {
        alert("Token JWT manquant, impossible d'envoyer le tracking");
        console.error("Token JWT manquant, impossible d'envoyer le tracking");
        return;
    }

    fetch("http://localhost:8000/api/visits", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
        },
        credentials: "include",
        body: JSON.stringify({ pageName, visitDate }),
    })
        .then((response) => {
            if (!response.ok) throw new Error(`Erreur ${response.status}`);
            return response.json();
        })
        .then((data) => {
            // alert("Tracking envoyé avec succès");
            console.log("Tracking envoyé:", data);
        })
        .catch((error) => {
            // alert("Erreur lors de l'envoi du tracking");
            console.error("Erreur tracking:", error);
        });
});
