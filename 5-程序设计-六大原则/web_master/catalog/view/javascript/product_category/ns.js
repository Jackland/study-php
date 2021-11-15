!function (s) {
    s.fn.nsHover = function (n) {
        function t(n, i, t) {
            if ("scale" === i) {
                var o = 1;
                t || (o = 0), n.stop().animate({scale: o}, {
                    step: function (n) {
                        a(s(this), n)
                    }, duration: c
                }, "ease-out")
            } else"slide" === i ? t ? n.stop().slideDown(c) : n.stop().slideUp(c) : t ? n.stop().fadeIn(c) : n.stop().fadeOut(c)
        }

        function a(s, n) {


            s.css("-webkit-transform", "scale(" + n + "," + n + ")"), s.css("-ms-transform", "scale(" + n + "," + n + ")"), s.css("transform", "scale(" + n + "," + n + ")")
        }

        function o(s, n) {
            s.css("-webkit-border-radius", n), s.css("-moz-border-radius", n), s.css("border-radius", n)
        }

        function e(s) {
            s.css("-webkit-box-shadow", "rgba(0,0,0,0.8) 0 0 3px"), s.css("-moz-box-shadow", "rgba(0,0,0,0.8) 0 0 3px"), s.css("box-shadow", "rgba(0,0,0,0.8) 0 0 3px")
        }

        function r(s, n) {
            var i = "", t = "rgab(0,0,0," + n + ")", a = /^#?([a-f\d])([a-f\d])([a-f\d])$/i;
            s = s.replace(a, function (s, n, i, t) {
                return n + n + i + i + t + t
            });
            var o = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(s);
            return i = o ? {
                r: parseInt(o[1], 16),
                g: parseInt(o[2], 16),
                b: parseInt(o[3], 16)
            } : null, t = "(" + i.r + "," + i.g + "," + i.b + "," + n + ")", "rgba" + t
        }

        var c, l, d = s.extend({
                scaling: !1,
                speed: "normal",
                rounded: "normal",
                shadow: !1,
                bganim: "fade",
                bgcolor: "#ffffff",
                bgopacity: .5,
                bgpic: "imgs/lens.png",
                bgsize: "25%",
                content: ""
            }, n), f = d.rounded, b = d.scaling, h = d.speed, p = d.shadow, u = d.bgcolor, g = d.bgopacity, m = d.bgpic,
            k = d.bgsize, v = d.content, w = d.bganim;
        "slow" === h ? (l = .5, c = 500) : "fast" === h ? (l = .2, c = 200) : (l = .3, c = 300), this.children().addClass("nsblock"), this.find("img").wrap('<div class="nsblock"></div>'), this.find(".nsblock").append('<span class="nsoverlay">' + v + "</span>"), this.find(".nsblock").each(function (n) {
            i = n + 1, s(this).attr("id", "nsblock" + i)
        }), this.find(".nsoverlay").html('<span class="nscontent">' + v + "</span>"), this.find(".nsblock").css({
            position: "relative",
            display: "inline-block",
            cursor: "pointer",
            overflow: "hidden"
        }), this.find(".nsblock img").css({
            position: "absolute",
            top: "0px",
            left: "0px",
            margin: "0",
            padding: "0"
        }), this.find(".nsoverlay").css({
            position: "absolute",
            display: "block",
            width: "100%",
            height: "100%",
            top: 0,
            left: 0,
            "background-image": "url(" + m + ")",
            "background-color": r(u, g),
            "background-repeat": "no-repeat",
            "background-size": k,
            "background-position": "center",
            "z-index": "9999"
        }), this.find(".nsoverlay").css("scale" === w ? {
            "-webkit-transform": "scale(0,0)",
            "-moz-transform": "scale(0,0)",
            "-ms-transform": "scale(0,0)",
            transform: "scale(0,0)"
        } : {display: "none"}), this.find(".nscontent").css({
            position: "absolute",
            "text-align": "center",
            width: "100%",
            top: "50%",
            "-webkit-transform": "translateY(-50%)",
            "-ms-transform": "translateY(-50%)",
            transform: "translateY(-50%)"
        }), this.find("img").each(function () {
            s(this).parent().css("width", s(this).width()), s(this).parent().css("height", s(this).height())
        });
        var y = "5%";
        "none" === f ? y = "0%" : "circle" === f && (y = "50%"), o(this.find(".nsblock"), y), o(this.find(".nsoverlay"), y), p && e(this.find(".nsblock")), this.find(".nsblock").css({
            WebkitTransition: "all " + l + "s ease-out",
            MozTransition: "all " + l + "s ease-out",
            MsTransition: "all " + l + "s ease-out",
            OTransition: "all " + l + "s ease-out",
            transition: "all " + l + "s ease-out"
        }), this.find(".nsblock").on("mouseenter", function () {
            b && a(s(this), b);
            var n = s(this).find(".nsoverlay");
            t(n, w, !0)
        }), this.find(".nsblock").on("mouseleave", function () {
            b && a(s(this), 1);
            var n = s(this).find(".nsoverlay");
            t(n, w, !1)
        })
    }
}(jQuery);