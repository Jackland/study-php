$(document).ready(function () {
    let second_menu_dom = document.getElementsByClassName("second_menu");
    let third_menu_dom = document.getElementsByClassName("third_menu");
    for(let i = 0; i < second_menu_dom.length; i++){
        second_menu_dom[i].onmouseover = function (e) {
            for(let i = 0; i < third_menu_dom.length; i++){
                third_menu_dom[i].style.display='none';
            }
            let target = e.srcElement ? e.srcElement : e.target;
            let id = target.name;
            document.getElementById(id).style.display='block';
        }
    }
})
