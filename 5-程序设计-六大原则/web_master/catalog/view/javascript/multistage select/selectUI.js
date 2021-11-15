$.fn.FMulSelect = function(A) {
    var B = this;
    var C = A.levels || 3;
    var D = A.width;
    var E = A.height || 32;
    var F = A.data || [];
    var G = A.levelNames || [];
    var H = A.dataKeyNames;
    var I = H['id'] || 'id';
    var J = H['name'] || 'name';
    var K = H['childs'] || 'childs';
    E -= 2;
    B.addClass('FMulSelectUI').width(D).height(E).data('source', F).attr('data-levels', C);
    B.find('.FMulSelectBox').off('click').remove().end().find('.FMulSelectUI-dropdown').off('click').remove();
    var L = $('<p class="FMulSelectBox" style="height: ' + E + 'px;line-height: ' + E + 'px;"></p>');
    var M = new Array(C).join(',').split(',');
    var N = $.map(M, function(v, k) {
        return '<span class="FMulSelectBox-items" data-id="" data-text="" data-level="' + (k + 1) + '">请</span>'
    }).join('<span class="FMulSelectBox-items-split">/</span>');
    L.append(N);
    B.append(L);
    var O = $('<ul class="FMulSelectUI-dropdown FMulSelectUI-hide"></ul>');
    var P = $.map(M, function(v, k) {
        return '<li class="FMulSelectUI-dropdown-levelItems ' + (k === 0 ? '' : 'FMulSelectUI-hide') + '" data-level="' + (k + 1) + '"><ul><li class="FMulSelectUI-nodata">无数据</li></ul></li>';
    }).join('');
    O.append(P);
    B.find('.FMulSelectUI-dropdown').remove().end().append(O);
    O.find('.FMulSelectUI-dropdown-levelItems:eq(0) ul').html('').append($.map(F, function(v, k) {
        return '<li class="FMulSelectUI-dropdown-levelItem" data-id="' + v[I] + '" data-text="' + v[J] + '">' + v[J] + '</li>'
    }).join('')).find('.FMulSelectUI-dropdown-levelItem').each(function(k, v) {
        $(this).data('source', F[k][K])
    });
    L.on('click',function () {
        // $(".FMulSelectBox > span").css("display","none");
        // $(".FMulSelectBox > span:first-child").css("display","block");
    });
    L.on('click', '.FMulSelectBox-items', function() {
        var Q = $(this).attr('data-level');
        O.removeClass('FMulSelectUI-hide');
        O.find('.FMulSelectUI-dropdown-levelItems').addClass('FMulSelectUI-hide').eq(Q - 1).removeClass('FMulSelectUI-hide');
        $(this).addClass('active').siblings('.FMulSelectBox-items').removeClass('active');
        var R = $('.FMulSelectUI').not('#' + B.attr('id'));
        if (R.length) {
            R.FMulSelectClear().find('.FMulSelectUI-dropdown').addClass('FMulSelectUI-hide');
        }
    });
    O.on('click', '.FMulSelectUI-dropdown-levelItem', function() {
        $(this).siblings().removeClass('active').end().addClass('active');
        var S = $(this).closest('.FMulSelectUI-dropdown-levelItems');
        var Q = S.attr('data-level');
        var U = $(this).attr('data-id');
        var V = $(this).attr('data-text');
        var F = $(this).data('source');
        L.find('.FMulSelectBox-items').removeClass('active').eq(Q - 1).attr('data-id', U).attr('data-text', V).html(V).end().eq(Q).addClass('active').end().eq(Q - 1).nextAll('.FMulSelectBox-items').attr('data-id', '').attr('data-text', '').html('请选择');
        if (S.next().length) {
            S.next().removeClass('FMulSelectUI-hide').siblings().addClass('FMulSelectUI-hide');
        }
        S.nextAll().find('ul').html('<li class="FMulSelectUI-nodata">无数据</li>');
        S.next().find('ul').html('').append($.map(F, function(v, k) {
            return '<li class="FMulSelectUI-dropdown-levelItem" data-id="' + v[I] + '" data-text="' + v[J] + '">' + v[J] + '</li>'
        }).join('') || '<li class="FMulSelectUI-nodata">无数据</li>').find('.FMulSelectUI-dropdown-levelItem').each(function(k, v) {
            $(this).data('source', F[k][K])
        });
        // $(".FMulSelectBox > span:first-child").css("display","none");
        // $(".FMulSelectBox > span:last-child").css("display","block");
    });
    $('body').on('click', function(e) {
        if (!$(e.target).hasClass('FMulSelectUI') && !$(e.target).closest('.FMulSelectUI').length) {
            $('.FMulSelectUI-dropdown').addClass('FMulSelectUI-hide');
            $('.FMulSelectUI').find('.FMulSelectBox-items').removeClass('active')
        }
    });

    return this
};
$.fn.FMulSelectGetVal = function() {
    return $.map(this.find('.FMulSelectBox-items'), function(v, k) {
        return $(v).attr('data-id');
    }).join('|');
};
$.fn.FMulSelectGetValTxt = function() {
    return $.map(this.find('.FMulSelectBox-items'), function(v, k) {
        return $(v).attr('data-text');
    }).join('|');
};
$.fn.FMulSelectClear = function() {
    this.find('.FMulSelectBox-items').attr('data-id', '').attr('data-text', '').html('请选择').end().find('.FMulSelectUI-dropdown-levelItem').removeClass('active');
    this.find('.FMulSelectBox-items').removeClass('active');
    return this
};
$.fn.FMulSelectSetVal = function(A) {
    this.FMulSelectClear();
    var B = this.find('.FMulSelectUI-dropdown-levelItems ');
    $.each(A, function(k, v) {
        B.eq(k).find('.FMulSelectUI-dropdown-levelItem[data-id=' + v + ']').trigger('click');
    });
    return this
};