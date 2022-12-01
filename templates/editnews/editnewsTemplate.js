$(document).ready(function () {

    if (window.location.href.includes("&submitted=saved")) {
        createSaveMessage();
    }
    if (window.location.href.includes("&submitted=deleted")) {
        createDeleteMessage();
    }
    
});
async function createSaveMessage() {
    $('.save-message')[0].style.display = "block";
    await new Promise(r => setTimeout(r, 4000));
    $('.save-message')[0].style.animation = "fadeOutAnimation 1s";
    await new Promise(r => setTimeout(r, 1000));
    $('.save-message')[0].style.display = "none";

    // removes the param from url without reloading
    window.history.pushState({}, '', window.location.href.replace("&submitted=saved", ""));
}
async function createDeleteMessage() {
    $('.delete-message')[0].style.display = "block";
    await new Promise(r => setTimeout(r, 4000));
    $('.delete-message')[0].style.animation = "fadeOutAnimation 1s";
    await new Promise(r => setTimeout(r, 1000));
    $('.delete-message')[0].style.display = "none";

    // removes the param from url without reloading
    window.history.pushState({}, '', window.location.href.replace("&submitted=deleted", ""));
}