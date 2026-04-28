document.addEventListener('DOMContentLoaded', () => {
    const runScript = () => {
        // Check if the device width is smaller than 768px
        const isSmallDevice = window.matchMedia('(max-width: 767px)').matches;

        if (!isSmallDevice) {
            // If the device is 768px or larger, fetch all inputs from .hp-explore-list
            console.log('Device width is 768px or larger. Fetching inputs from .hp-explore-list...');
            const exploreList = document.querySelector('.hp-explore-list');
            if (exploreList) {
                const inputs = exploreList.querySelectorAll('.hp-explore-input');
                
                // Perform operations on each input
                inputs.forEach(input => {
                    input.checked = true
                });
            }
        }
    };

    // Run the script initially
    runScript();

    // Add an event listener for viewport changes
    window.addEventListener('resize', () => {
        console.log('Viewport size changed. Re-running the script...');
        runScript(); // Re-run the script when the viewport size changes
    });
});