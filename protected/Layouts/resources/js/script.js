var APP = APP || {};
// APP.settings = APP.settings || {};
// APP.popups = APP.popups || [];
APP.openPopup = function (html) {
    $("body").append(html);
    APP.currentPopup.index = APP.popups.length - 1;
    APP.currentPopup.obj = APP.popups[APP.currentPopup.index];
    APP.currentPopup.JQobj = $(APP.popups[APP.currentPopup.index].popupSelector);
    APP.currentPopup.JQobj.dialog({
        autoOpen: true,
        height: "auto",
        width: APP.popups[APP.currentPopup.index].width || "auto",
        modal: true,
        close: function (event, ui) {
            var reloadParent = APP.currentPopup.reloadParent;
            APP.currentPopup.JQobj.dialog("destroy");
            APP.currentPopup.JQobj.remove();
            APP.popups.pop();
            APP.currentPopup.index = (APP.popups.length > 0) ? APP.popups.length - 1 : undefined;
            APP.currentPopup.JQobj = (undefined !== APP.currentPopup.index) ? $(APP.popups[APP.currentPopup.index].popupSelector) : undefined;
            if (true === reloadParent) {
                APP.refresh();
            }
        }
    })
};

APP.closePopup = function ()
{
    APP.currentPopup.JQobj.dialog("close");
};

APP.refresh = function () {
    //если текущее окно не popup, то reload страницы
    //если popup - запрашиваем текущее окно заново (при наличии APP.currentPopup.path у текущего окна )
    if (undefined === APP.currentPopup.index) {
        location.reload();
    } else if (undefined !== APP.currentPopup.path) {
        var path = APP.currentPopup.path;
        APP.closePopup();
        APP.getPopup(path);
    }
};

APP.getPopup = function (href) {
    $.ajax({
        url: href,
        type: "GET",
        dataType: "html"
    })
        .done(function (html) {
            APP.openPopup(html);
        })
};

APP.request = function (href, data) {
    var requestType = (undefined === data) ? "GET" : "POST";
    $.ajax({
        url: href,
        type: requestType,
        data: data,
        dataType: "html"
    })
        .done(function (data, textStatus, jqXHR) {
            console.log("done in request");
            //если POST запрос, то закрываем форму и открываем окно результат
            //если GET запрос, значит окна формы нет и сразу открываем окно результата
            if ("POST" == this.type) {
                APP.closePopup();
            }
            APP.openPopup(data); //вывод результата submit
            APP.currentPopup.reloadParent = true; //нужно перегрузить родителя для отоображения изменений
        })
        .fail(function ( jqXHR, textStatus, errorThrown) {
            console.log("fail");
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        })
};

APP.convertTime = function() {
    var dateFormater = new Intl.DateTimeFormat("ru", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit"
    });

    var timeFormater = new Intl.DateTimeFormat("ru", {
        hour: "numeric",
        minute: "numeric",
        timeZoneName: "short"
    });


    $("span.lastUpdate").each(function () {
        var UTCtime = $(this).data("lastUpdateUtc"); //get UTC time as string from tag <span class="lastUpdate"> in format (2017-08-15 14:55:45 UTC)
        // console.log(UTCtime);
        var date = APP.dateToUtc(UTCtime);
        if (! isNaN(date.getDate())) {
            $(this).attr("title", "last update: " + dateFormater.format(date) + " " + timeFormater.format(date));
            $(this).html(dateFormater.format(date));
        }
    });

    $("td.lastUpdate").each(function () {
        var UTCtime = $(this).data("lastUpdateUtc"); //get UTC time as string from tag <span class="lastUpdate"> in format (2017-08-15 14:55:45 UTC)
        // console.log(UTCtime);
        var date = APP.dateToUtc(UTCtime);
        if (! isNaN(date.getDate())) {
            $(this).attr("title", "last update: " + dateFormater.format(date));
        }
    });
};

APP.ctrlCheckbox = function () {
    $("input[type=checkbox]").on(
        "change",
        function ($e) {
            var hiddenInput = '<input type="hidden" name="' + $(this).attr("name") + '" value="0">';
            var $parent = $(this).parent(".checkbox-container");
            if (this.checked) {
                $parent.children("input[type=hidden]").remove();
                console.log('checked');
            } else {
                $parent.prepend(hiddenInput);
                console.log('unchecked');
            }
        }
    )
};

APP.filterRegion = function () {
    $(".filter-list .dropdown-menu a").on(
        "click",
        function ($e) {
            $e.stopPropagation();
            var $regId;
            if ($(this).children("input[type=checkbox]").prop("checked")) {
                $(this).children("input[type=checkbox]").prop( "checked", false );
                $regId = $(this).data("regId");
                console.log($regId);
                if ($regId == "all") {
                    $("tr[data-reg-id]").hide();
                    // console.log($("td[data-reg-id]"));
                } else {
                    $("tr[data-reg-id=" + $regId + "]").hide();
                }
            } else {
                $(this).children("input[type=checkbox]").prop( "checked", true );
                $regId = $(this).data("regId");
                console.log($regId);
                if ($regId == "all") {
                    $("tr[data-reg-id]").show();
                    console.log("all on");
                } else {
                    $("tr[data-reg-id=" + $regId + "]").show();
                }
            }
        }
    )
};
APP.dateToUtc = function (date, toUTC) {
    var InPutdate = new Date(date);
    var LocalTime = InPutdate.getTime();
    var LocalOffsetTime = InPutdate.getTimezoneOffset() * 60000;
    // if true convert to utc else convert from utc to Local
    if (toUTC)
    {
        //Convert to UTC
        InPutdate = LocalTime + LocalOffsetTime;
    }
    else
    {
        //UTC to Normal
        InPutdate = LocalTime - LocalOffsetTime;
    }
    // console.log(new Date(InPutdate))
    return new Date(InPutdate);
};
APP.setLink = function ($linkSelector, $url) {
    $($linkSelector).attr("href", $url);
};

jQuery(function ($) {

    APP.currentPopup = APP.currentPopup || {};
    APP.ctrl = APP.ctrl || [];
    APP.body = APP.body || $('body');
    //APP.convertTime();
    APP.filterRegion();
    APP.body.on(
        "click",
        "a[role='button'][data-action='get-popup']", //открытие диалогового окна
        function (event) {
            event.stopPropagation();
            event.preventDefault();
            urlPopup = $(this).attr("href");
            APP.getPopup(urlPopup);
        }
    );
    APP.body.on(
        "click",
        "a[role='button'][data-action!='get-popup']", //запрос GET или POST
        function (event) {
            event.stopPropagation();
            event.preventDefault();
            url = $(this).attr("href");
            APP.request(url);
        }
    );

    APP.body.on(
        "click",
        "button[data-action]", //click on button with attribute "data-action"
        function (event) {
            var action = $(this).data("action");
            switch (action) {
                //закрытие диалогового окна
                case "close":
                    APP.currentPopup.JQobj.dialog("close");
            }
        }
    );
    
    APP.body.on(
        "submit", //submit form
        "form",
        function (event) {
            console.log("submit form");
            event.stopPropagation();
            event.preventDefault();
            data = $(this).serialize();
            APP.request($(this).attr('action'), data);
        }
    )

});

