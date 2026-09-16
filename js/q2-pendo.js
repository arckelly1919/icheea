// q2-pendo.js v2.8
(function(){
    (function(p, e, n, d, o) {
        var v, w, x, y, z;
        o = p[d] = p[d] || {};
        o._q = [];
        v = ['initialize', 'identify', 'updateOptions', 'pageLoad'];
        for (w = 0, x = v.length; w < x; ++w)(function(m) { o[m] = o[m] || function() { o._q[m === v[0] ? 'unshift' : 'push']([m].concat([].slice.call(arguments, 0))); }; })(v[w]);
        y = e.createElement(n);
        y.async = !0;
        y.src = 'https://cdn1.onlineaccess1.com/cdn/static/q2-pendo/pendo-2.340.0.js';
        z = e.getElementsByTagName(n)[0];
        z.parentNode.insertBefore(y, z);
    })(window, document, 'script', 'pendo');

    var uuxVersion = window.Q2_PRODUCTION_TAG || (window.Tecton && window.Tecton.UUX_VERSION) || '0.0.0.0';

    var customerNumber = (window.Q2_CONFIG && window.Q2_CONFIG.cdnCustomerNumber) || (window.Tecton && window.Tecton.FI_IDENTIFIER) || '';

    var apiKey = window.Q2_CONFIG && window.Q2_CONFIG.primary_pendo_key || window.Tecton && window.Tecton.pendo && window.Tecton.pendo.apiKey || '4cfc5253-789b-470f-45eb-e4d59dd0bf11';
    var additionalApiKeys = window.Q2_CONFIG && window.Q2_CONFIG.additional_pendo_key || window.Tecton && window.Tecton.pendo && window.Tecton.pendo.additionalApiKeys || [];
    var includePII = true;
    if (window.Q2_CONFIG && (window.Q2_CONFIG.pendo_include_pii === false)) {
        includePII = false;
    }

    var pendoInitialize = {
        apiKey: apiKey,
        additionalApiKeys: additionalApiKeys,
        visitor: {},
        account: {
            id: customerNumber,
            version: uuxVersion,
        }
    };

    function initPendo() { pendo.initialize(pendoInitialize); }

    function replaceSlash(name) {
        return name.replace("/", "_");
    }

    function updatePendo(response) {
        try {
            var visitor = {};
            var uuxConfiguration = Ngam.__container__.lookup('service:uux-configuration');
            var themeId = response.themeId;
            var themeName = themeId ? 'theme-' + themeId : window.Q2_CONFIG.themeName;
            var username = response.loginName || '';
            var isBillpayEnrolled = response.isBillpayEnrolled
            var billPayData = isBillpayEnrolled?.pairsField || [];
            var isBillPay = false;
            var features = response.capabilities?.features || [];

            for (var i =  billPayData.length - 1; i >= 0; i--) {
                if (billPayData[i].valueField === 'True') {
                    isBillPay = true;
                    break;
                }
            }

            visitor = {
                id: "ID" + customerNumber + '-' + response.userId,
                canPfm: response.capabilities?.canPFMAggregate || response.capabilities?.canPFMWidget || false,
                isPfm: response.capabilities?.isPfmEnrolled || false,
                isCSR: response.isCSR || false,
                isCsrAssist: response.isCsrAssist || false,
                themeName: themeName,
                highContrastEnabled: uuxConfiguration.config?.themeModifiers?.highContrast,
                fullName: includePII && (response.firstName || '') + ' ' +  (response.lastName || ''),
                isGoals: response.capabilities?.isGoalBasedAccountOpenEnabled || false,
                isBillPay: isBillPay,
                groupId: response.groupId,
                zoneId: response.zoneId,
                snippetVersion: 2.8
            };

            t = response.capabilities?.transactionRights ||  {};
            Object.keys(t).forEach(function(tran) { visitor[tran] = t[tran].enabled; });
            
            c = response.capabilities || {};
            Object.keys(c).forEach(function(cap) { if (cap === 'transactionRights' || cap === 'features' ) return; visitor[cap] = c[cap]; }) 

            features.forEach((feature) => {
                visitor[replaceSlash(feature.property)] = feature.value
                if(feature.property === 'FeatureGroupCustUser/MarketplaceStore') {
                    visitor['hasMarketplace'] = feature.value;
                }
            })

        } catch (e) {
            return;
        }

        if (response.userId === 0) return;

        if (!!username) {
            visitor.username = includePII && username;
        }

        pendo.updateOptions({
            visitor: visitor
        });
    }

    function checkMenu() {
        try {
            var visitorData = {};
            var menuData = wedgeIntegrationController.get('store.dataCache.entries.mobilews/nav.value.data');
            var menuContent = menuData && JSON.stringify(menuData);
            if (menuContent) {
                visitorData = {
                    canMarketplace: menuContent.includes('AppDirectSSO'),
                    hasAutobooks: menuContent.includes('"shortName":"AutobooksSSO"')
                };
            }
        } catch (e) {
            return;
        }

        pendo.updateOptions({
            visitor: visitorData
        });
    }

    function firstNavEventHandler() {
        try {
            var visitorData = {};

            var userService = Ngam.__container__.lookup('service:user');
            var userInfoService = Ngam.__container__.lookup('service:user-info');
            var userController = Ngam.__container__.lookup('controller:user');

            var user = userService || userInfoService || userController;

            if (user) {
                visitorData.isTreasury = user.isTreasury || false;
                visitorData.isCommercial = user.isCommercial || false;
            }

            if (visitorData.isCommercial) {
                var policyService = Ngam.__container__.lookup('service:policy');
                var policyController = policyService || userController;

                visitorData.customerName = policyController?.policy?.customer?.name;

                if (!visitorData.customerName) {
                    setTimeout(function () {
                        pendo.updateOptions({
                            visitor: {
                                customerName: policyController?.policy?.customer?.name
                            }
                        });
                    }, 1000);
                }
            }
        } catch (e) {
            return;
        }

        pendo.updateOptions({
            visitor: visitorData
        });
    }

    function ready(fn) {
        if (document.attachEvent ? document.readyState === "complete" : document.readyState !== "loading"){
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function teardownPendo(event) {
        window.pendo?.teardown?.();
        window.removeEventListener('pagehide', teardownPendo);
        window.removeEventListener('beforeunload', teardownPendo);
        console.info("Tearing down Pendo instance")
    }
    window.addEventListener('pagehide', teardownPendo);
    window.addEventListener('beforeunload', teardownPendo);

    var pendoRegistered = false;
    var pendoInited = false;

    var register = function() {
        if (!pendoInited && window.pendo) {
            initPendo();
            pendoInited = true;
        }
        if (window.Ngam && !pendoRegistered) {
            pendoRegistered = true;
            var loginFlow = Ngam.__container__.lookup('service:loginFlow');
            var menuContent = wedgeIntegrationController.get('store.dataCache.entries.mobilews/nav.value.data');
            var loginController = Ngam.__container__.lookup('controller:login');
            var loginFlowReq = loginController && loginController.get('loginFlowRequirements');
            if (loginFlow && loginFlow.authenticatedStatus === 200) {
                updatePendo(loginFlow);
            } else if (loginFlowReq && loginFlowReq.authenticatedStatus === 200) {
                updatePendo(loginFlowReq);
            } else {
                Ngam.__container__.lookup('controller:application').get('notificationCenter').on('POST_LOGIN', updatePendo);
            }

            if (menuContent) {
                checkMenu();
            } else {
                Ngam.__container__.lookup('controller:application').get('notificationCenter').on('MENU_RELOADED', checkMenu);
            }

            Ngam.__container__.lookup('controller:application').get('notificationCenter').one('NAVIGATION_OCCURED', firstNavEventHandler);
        }
    };
    window.addEventListener('load', register);
    ready(register);
    setTimeout(function() {
        register();
    });
    if (window.Ngam) {
        register();
    }
})();