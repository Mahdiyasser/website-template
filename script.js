function loadStyle() {
  // Remove old stylesheet if exists
  const oldLink = document.getElementById("dynamic-style");
  if (oldLink) oldLink.remove();

  // Create new link
  const link = document.createElement("link");
  link.rel = "stylesheet";
  link.id = "dynamic-style";

  // Pick stylesheet
  const aspect = window.innerWidth / window.innerHeight;
  if (aspect >= 1) {
    // Landscape → Desktop
    link.href = "style1.css";
  } else {
    // Portrait → Mobile
    link.href = "style2.css";
  }

  // Add it to head
  document.head.appendChild(link);
}

// Run on page load
window.addEventListener("load", loadStyle);

// Run on resize/orientation change
window.addEventListener("resize", loadStyle);

