
/* =========================
   LOAD THEME
========================= */

const savedTheme =
localStorage.getItem(
    "theme"
);

const toggle =
document.getElementById(
    "themeToggle"
);

if(savedTheme === "light"){

    document.body.classList.add(
        "light-mode"
    );

    toggle.checked = true;
}

/* =========================
   TOGGLE THEME
========================= */

toggle.addEventListener(
    "change",
    ()=>{

        if(toggle.checked){

            document.body.classList.add(
                "light-mode"
            );

        }else{

            document.body.classList.remove(
                "light-mode"
            );
        }
    }
);

/* =========================
   SAVE SETTINGS
========================= */

document.getElementById(
    "saveBtn"
).addEventListener(
    "click",
    ()=>{

        localStorage.setItem(
            "theme",
            toggle.checked
            ? "light"
            : "dark"
        );

        const btn =
        document.getElementById(
            "saveBtn"
        );

        btn.innerHTML =
        '<i class="fas fa-check"></i> Saved';

        setTimeout(()=>{

            btn.innerHTML =
            '<i class="fas fa-check-circle"></i> Save Settings';

        },1500);
    }
);
