function showNotification(from, align, type, message){
    $.notify({
        icon: "pe-7s-bell",
        message: message
    },{
        type: type,
        timer: 4000,
        placement: {
            from: from,
            align: align
        }
    });
}

function openNested(ul) {
    ul.style.display = "block";
    var span = ul.parentElement ? ul.parentElement.querySelector(".box") : null;
    if (span) span.classList.add("check-box");
}

function closeNested(ul) {
    ul.style.display = "none";
    var span = ul.parentElement ? ul.parentElement.querySelector(".box") : null;
    if (span) span.classList.remove("check-box");
}

document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        // ซ่อนทุก nested ก่อน แล้วเปิดเฉพาะที่ active
        document.querySelectorAll(".nested").forEach(function(ul) {
            var isActive = ul.classList.contains("active") || ul.querySelector("li.active") !== null;
            if (isActive) {
                openNested(ul);
            } else {
                ul.style.display = "none";
            }
        });

        // Click toggle
        document.querySelectorAll(".box").forEach(function(span) {
            span.addEventListener("click", function() {
                var nested = this.parentElement ? this.parentElement.querySelector(".nested") : null;
                if (!nested) return;
                if (nested.style.display === "block") {
                    closeNested(nested);
                } else {
                    openNested(nested);
                }
            });
        });

        // Scroll sidebar ให้ active item อยู่กึ่งกลางหน้าจอ
        var activeItem = document.querySelector(".sidebar-wrapper li.active");
        if (activeItem) {
            var wrapper = document.querySelector(".sidebar-wrapper");
            if (wrapper) {
                var itemTop = activeItem.offsetTop;
                var itemHeight = activeItem.offsetHeight;
                var wrapperHeight = wrapper.clientHeight;
                wrapper.scrollTop = itemTop - (wrapperHeight / 2) + (itemHeight / 2);
            }
            activeItem.scrollIntoView({ behavior: "smooth", block: "left" });
        }
    }, 300);
});