(function () {

    const LATTICEAI_ENV = (function () {
        if (window.LATTICE_API.includes("api.latticeai.io") || window.LATTICE_API.includes("api.shopagain.io")) {
            return "prod";
        }
        else if (window.LATTICE_API.includes("mercuryapi-staging.latticeai.io")) {
            return "staging";
        }
        else {
            return "dev";
        }
    })();

    function getFullCookieName(cookieName) {
        if (LATTICEAI_ENV !== 'prod') {
            return `latticeai-popups-${LATTICEAI_ENV}-${cookieName}`
        }

        return `latticeai-popups-${cookieName}`
    }

    function read_cookie(name, exactName = false) {
        if (!exactName)
            name = getFullCookieName(name)
        var result = document.cookie.match(new RegExp(name + '=([^;]+)'));
        result && (result = JSON.parse(result[1]));
        return result;
    }

    const apiBase = window.LATTICE_API;
    const currentScript = document.currentScript;
    const src = currentScript.src
    const scriptUrl = new URL(src)
    const scriptSearchParams = scriptUrl.searchParams
    const shop = scriptSearchParams.get('shop')

    const fetchProductsAndRender = async () => {
        let cartIds = []

        if (window.location.href.includes("/cart") && window.Shopify && window.Shopify.routes && window.Shopify.routes.root) {
            const settings = await (await fetch('/cart.js')).json()
            if (settings && settings.items && settings.items.length)
                cartIds = settings.items.map(item => item.product_id)
        }

        const url = new URL(`campaigns/get_widget_products/`, apiBase).toString();

        fetch(url, {
            method: 'POST',
            body: JSON.stringify({
                shop: shop,
                page_url: window.location.href,
                pid: read_cookie('pid'),
                uid: read_cookie('uid'),
                cart_product_ids: cartIds,
                script_query_params: Object.fromEntries(scriptSearchParams),
            }),
            headers: {
                'Content-Type': 'application/json'
            },
        }).then(resp => resp.json()).then(data => {
            const css = data.css;
            const style = document.createElement("style");
            style.innerHTML = css;
            document.head.appendChild(style);
            data.widgets.forEach((widget) => {
                const widgetType = widget.type;
                const className = widget.className;
                const html = widget.html;
                let container = document.querySelector("." + className);
                if (!container) {
                    container = document.createElement("div");
                    container.setAttribute("class", widget.className);
                    let containerClassName = function () {
                        if (LATTICEAI_ENV === "prod") {
                            return "shopagain_widgets_all";
                        }
                        else {
                            return `shopagain_widgets_all_${LATTICEAI_ENV}`;
                        }
                    }();
                    console.log({containerClassName})
                    let parentContainer = document.querySelector(`.${containerClassName}`);
                    console.log({parentContainer})
                    if (!parentContainer) {
                        parentContainer = document.querySelector("main");
                    }
                    if (parentContainer) {
                        parentContainer.append(container);
                    }
                    // const footer = document.querySelector("footer");
                    // footer.parentElement.insertBefore(container, footer);

                }
                container.innerHTML = html;

                container.querySelectorAll(".shopagain-product-card").forEach((button) => {
                    button.setAttribute("data-widget-type", widgetType);
                });
            });

            const js = data.js;
            const script = document.createElement("script");
            script.setAttribute("data-base-url", apiBase);
            script.setAttribute("data-pid-cookie-name", getFullCookieName('pid'));
            script.setAttribute("data-shop", shop);
            script.innerHTML = js;
            document.head.appendChild(script);

        });
    }

    fetchProductsAndRender();

})();
