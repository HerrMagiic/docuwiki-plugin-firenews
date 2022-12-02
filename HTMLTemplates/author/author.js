let count = 0;
$(document).ready(function () {
    //Default Start date value
    let today = new Date().toISOString().split('T')[0];
    $("#lstartdate").val(today);

    //Default end date value minus one month
    let todayminusOneMonth = new Date();
    todayminusOneMonth.setMonth(todayminusOneMonth.getMonth() + 1);
    $("#lenddate").val(todayminusOneMonth.toISOString().split('T')[0]);

    // Sets the values in the preview
    const author = document.getElementsByClassName("lauthor")[0].value;

    document.getElementsByClassName("date-author")[0].innerHTML = `( ${today}, ${author} )`;

    // selecting the elements for which we want to add a tooltip
    const target = document.getElementById("target-icon");
    const tooltip = document.getElementById("tooltip-text");

    // change display to 'block' on mouseover
    target.addEventListener('mouseover', () => {
        tooltip.style.display = 'block';
    }, false);

    // change display to 'none' on mouseleave
    target.addEventListener('mouseleave', () => {
        tooltip.style.display = 'none';
    }, false);

    if (window.location.href.includes("&submitted=true")) {
        createInfoMessage();
    }
    if (window.location.href.includes("&fileexists=false")) {
        pageMissing();
    }
    
});
async function pageMissing() {
    $('.error-message')[0].style.display = "block";
    await new Promise(r => setTimeout(r, 4000));
    $('.error-message')[0].style.animation = "fadeOutAnimation 1s";
    await new Promise(r => setTimeout(r, 1000));
    $('.error-message')[0].style.display = "none";

    // removes the param from url without reloading
    window.history.pushState({}, '', window.location.href.replace("&fileexists=false", ""));
}
/**
 * creates a pop up message when
 */
async function createInfoMessage() {
    $('.info-message')[0].style.display = "block";
    await new Promise(r => setTimeout(r, 4000));
    $('.info-message')[0].style.animation = "fadeOutAnimation 1s";
    await new Promise(r => setTimeout(r, 1000));
    $('.info-message')[0].style.display = "none";

    // removes the param from url without reloading
    window.history.pushState({}, '', window.location.href.replace("&submitted=true", ""));
}
/**
 * adds bold into the textarea
 */
function addBold() {
    document.getElementById("lnews").value += "<b></b>";
}
/**
 * adds italic into the textarea
 */
function addItalic() {
    document.getElementById("lnews").value += "<i></i>";
}
/**
 * adds underline into the textarea
 */
function addUnderline() {
    document.getElementById("lnews").value += "<u></u>";
}

function HeaderChange() {
    document.getElementsByClassName("header")[0].innerHTML = document.getElementsByClassName("lheader")[0].value;
}
function SubtitleChange() {
    document.getElementsByClassName("subtitle")[0].innerHTML = document.getElementsByClassName("lsubtitle")[0].value;
}
function DateAuthorChange() {
    const startdate = document.getElementsByClassName("lstartdate")[0].value;
    const author = document.getElementsByClassName("lauthor")[0].value;

    document.getElementsByClassName("date-author")[0].innerHTML = `( ${startdate}, ${author} )`;
}
function NewsChange() {
    document.getElementsByClassName("news")[0].innerHTML = document.getElementsByClassName("textlnews")[0].value;
}