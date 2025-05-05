document.addEventListener("DOMContentLoaded", () => {
    // Gestion du menu mobile
    const mobileMenuToggle = document.getElementById("mobile-menu-toggle")
    const mobileMenu = document.getElementById("mobile-menu")
  
    if (mobileMenuToggle && mobileMenu) {
      mobileMenuToggle.addEventListener("click", () => {
        mobileMenu.classList.toggle("active")
      })
    }
  
    // Fermeture des alertes
    const alertCloseButtons = document.querySelectorAll(".alert-close")
  
    alertCloseButtons.forEach((button) => {
      button.addEventListener("click", function () {
        const alert = this.parentElement
        alert.style.display = "none"
      })
    })
  
    // Gestion des onglets
    const tabButtons = document.querySelectorAll(".tab-btn")
    const tabContents = document.querySelectorAll(".tab-content")
  
    if (tabButtons.length > 0 && tabContents.length > 0) {
      tabButtons.forEach((button) => {
        button.addEventListener("click", function () {
          const tabId = this.getAttribute("data-tab")
  
          // Désactiver tous les onglets
          tabButtons.forEach((btn) => btn.classList.remove("active"))
          tabContents.forEach((content) => content.classList.remove("active"))
  
          // Activer l'onglet sélectionné
          this.classList.add("active")
          document.getElementById(tabId).classList.add("active")
        })
      })
    }
  
    // Mise à jour de l'heure en temps réel
    const clockDisplay = document.querySelector(".date-display")
  
    if (clockDisplay) {
      setInterval(() => {
        const now = new Date()
        const timeString = now.toLocaleTimeString()
        const clockElement = clockDisplay.querySelector("span") || clockDisplay
  
        if (clockElement.textContent.includes(":")) {
          clockElement.textContent = timeString
        }
      }, 1000)
    }
  })
  