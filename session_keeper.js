
// Session keeper - keeps session alive (minimal version)
(function() {
    "use strict";
    
    // Keep session alive every 30 minutes (much less frequent)
    setInterval(function() {
        fetch("keep_session_alive.php", {
            method: "GET",
            credentials: "same-origin"
        }).then(response => {
            if (!response.ok) {
                console.warn("Session keep-alive failed (but no logout will occur)");
            }
        }).catch(error => {
            console.error("Session keep-alive error (but no logout will occur):", error);
        });
    }, 1800000); // Every 30 minutes instead of 5 minutes
    
    console.log("Session keeper initialized (no timeouts - sessions persist until browser close)");
})();
