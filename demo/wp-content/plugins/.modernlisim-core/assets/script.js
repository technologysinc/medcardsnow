document.addEventListener("DOMContentLoaded", function () {
    let currentUrl = window.location.href;
    let style = document.createElement("style");
    
    if (currentUrl.includes("admin.php?page=modernlisim_core")) {
        // Apply styles for the Modernlisim Core page
        style.innerHTML = `
            #wpcontent {
                height: 100%;
                padding-left: 0 !important;
            }
            .wrap {
                margin: 0px 0px 0 0px !important;
            }
            .wp-core-ui .notice.is-dismissible {
                padding-right: 38px;
                position: relative;
            }
        `;
    } else {
        // Apply styles for all other admin pages
        style.innerHTML = `
            .wrap {
                margin: 20px 20px 0px 20px !important;
            }
        `;
    }

    document.head.appendChild(style);
});
