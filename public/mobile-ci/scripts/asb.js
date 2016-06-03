
var lastNoNullPosition = 0,
    startChar,
    startClientY;

var initializeAsb = function () {
    var promises = [],
        supportedAmount = Math.floor($('.asb-content').height() / 22),
        strArr;

    if (supportedAmount <= 10) {
        strArr = "#,a,b,cdefghi,j,klmnopqr,s,tuvwx,y,z".split(','); // 10
    }
    else if (supportedAmount <= 12) {
        strArr = "#,a,b,cdefgh,i,j,klmnopq,r,s,tuvwx,y,z".split(','); // 12
    }
    else if (supportedAmount <= 14) {
        strArr = "#,a,b,c,defghi,j,k,lmnopqr,s,tuvwx,y,z".split(','); // 14
    }
    else if (supportedAmount <= 17) {
        strArr = "#,a,b,c,d,efghi,j,k,l,mnopqr,s,t,uvwx,y,z".split(','); // 17
    }
    else if (supportedAmount <= 19) {
        strArr = "#,a,b,c,d,efgh,i,j,k,l,mnopq,r,s,t,uvwx,y,z".split(','); // 19
    }
    else if (supportedAmount <= 21) {
        strArr = "#,a,b,c,def,g,h,i,j,k,lmn,o,p,qrs,t,u,v,w,x,y,z".split(','); // 21
    }
    else if (supportedAmount <= 23) {
        strArr = "#,a,b,c,de,f,g,h,i,j,k,lmn,o,p,qr,s,t,u,v,w,x,y,z".split(','); // 23
    }
    else if (supportedAmount <= 25) {
        strArr = "#,a,b,c,d,ef,g,h,i,j,k,l,m,n,o,p,qr,s,t,u,v,w,x,y,z".split(','); // 25
    }
    else {
        strArr = "#abcdefghijklmnopqrstuvwxyz".split(''); // 27
    }

    for (var i = 0; i < strArr.length; i++) {
        var deferred = new $.Deferred();

        var text = strArr[i].length > 1 ? '-' : strArr[i].toUpperCase();
        var data = strArr[i].toUpperCase();

        var $btn = $('<a />').attr({
            'class': 'btn asb-btn disabled',
            'href': '#',
            'data-index': data
        })
        .text(text);

        $('#asb').append($btn);

        deferred.resolve();
        promises.push(deferred);
    }

    return $.when.apply(undefined, promises).promise();
};

var getScrollTopByIndex = function (str) {
    var result = null;
    if (str.length === 1) {
        var char = str[0].toLowerCase();
        result = scrollCatalogue[char];
    }
    else if (str.length > 1) {
        for (var i = 0; i < str.length; i++) {
            var char = str[i].toLowerCase();
            if (scrollCatalogue[char]) {
                result = scrollCatalogue[char];
                break;
            }
        }
    }

    if (result){
        lastNoNullPosition = result;
    }
    else if (!result && !lastNoNullPosition) {
        for (var i in scrollCatalogue) {
            lastNoNullPosition = scrollCatalogue[i];
            result = scrollCatalogue[i];
            break;
        }
    }
    else {
        result = lastNoNullPosition;
    }

    return result;
};

var scrollToChar = function (char) {
    var toScrollPos = scrollCatalogue[char.toLowerCase()];

    var $info = $('.scroll-info');
    $info.html(char.toUpperCase());
    $info.stop(true, true).show().delay(300).fadeOut();

    $(window).scrollTop(toScrollPos);
};

var getCharByScrollTop = function (scrollTop) {
    for (var i in scrollCatalogue) {
        if (scrollCatalogue[i] === scrollTop) {
            return i;
        }
    }
    return '#';
};

var bindAsbEvents = function () {
    var promises = [],
        supportedHeight = $('.asb-content').height(),
        scrollArr = Object.keys(scrollCatalogue);

    $('#asb > .btn[data-index]').each(function () {
        var deferred = new $.Deferred();
        var $btn = $(this);
        var dataIndex = $btn.data('index');
        var scrollTop = getScrollTopByIndex(dataIndex);

        if (scrollTop) {
            $btn.on('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                scrollToChar(getCharByScrollTop(scrollTop));
            })
            .on('touchstart', function (ev) {
                var touch = ev.originalEvent.changedTouches[0];
                startChar = getCharByScrollTop(scrollTop);
                startClientY = touch.clientY;
            })
            .on('touchend', function (ev) {
                startChar = null;
                startClientY = null;
            })
            .on('touchmove', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();

                var touch = ev.originalEvent.changedTouches[0];
                var clientY = touch.clientY;

                if (startChar && startClientY) {
                    var totalVerticalOffset = clientY - startClientY;
                    var proximity = Math.floor(supportedHeight / scrollArr.length);
                    var indexOffset = Math.floor(totalVerticalOffset / proximity);

                    var targetIndex = scrollArr.indexOf(startChar) + indexOffset;
                    var targetChar = scrollArr[targetIndex];

                    if (targetChar) {
                        scrollToChar(targetChar);
                    }
                }
            });
        }
        deferred.resolve();
        promises.push(deferred);
    });

    return $.when.apply(undefined, promises).promise();
};

var enableAsb = function () {
    $('#asb > .btn[data-index]').removeClass('disabled');
};

$(window).on('orientationchange', function () {
    $('#asb').empty();
    setTimeout(function () {
        initializeAsb().done(function () {
            bindAsbEvents().done(function () {
                enableAsb();
            });
        });
    }, 300);
});

initializeAsb();