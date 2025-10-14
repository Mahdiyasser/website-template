document.addEventListener('DOMContentLoaded', () => {
    const toggleButton = document.getElementById('theme-toggle');
    const body = document.body;
    const toggleIcon = document.getElementById('toggle-icon');

    // 1. Check local storage for saved theme preference
    const savedTheme = localStorage.getItem('theme');
    
    // 2. Function to apply the theme
    const applyTheme = (theme) => {
        if (theme === 'light') {
            body.classList.add('light-theme');
            toggleIcon.textContent = '🌙'; // Moon icon for light mode (click to go dark)
        } else {
            body.classList.remove('light-theme');
            toggleIcon.textContent = '💡'; // Lightbulb icon for dark mode (click to go light)
        }
    };

    // 3. Apply the saved theme or default to dark
    if (savedTheme) {
        applyTheme(savedTheme);
    } else {
        // Optional: Check system preference (prefers-color-scheme)
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
            applyTheme('light');
        } else {
            applyTheme('dark');
        }
    }

    // 4. Add event listener to toggle the theme
    toggleButton.addEventListener('click', () => {
        const currentTheme = body.classList.contains('light-theme') ? 'light' : 'dark';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        applyTheme(newTheme);
        
        // Save the new preference to local storage
        localStorage.setItem('theme', newTheme);
    });
});
