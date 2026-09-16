define('q2ngam/themejs/theme-q2',["exports"], function(__exports__) {
var themeJS = {
    themeName: "theme-q2",
    cssName: "q2",
    lpColorMap: {
        "C": "#7d9638",
        "S": "#d99741",
        "L": "#dd8862",
        "X": "#a53326",
        "defaultColor": "#a53326"
    },
    widgets: [{
            page: "dashboard",
            location: "account-group",
            devices: ["tablet", "desktop", "phone"],
            widgets: [{
                    name: "SmartAd",
                    spaceId: 7
                }, ]
        }, {
            page: "dashboard",
            location: "account-card",
            devices: ["tablet", "desktop", "phone"],
            widgets: [{
                    name: "SmartAd",
                    spaceId: 6
                }, ]
        }, {
            page: "accounts",
            location: "account-detail-middle",
            orientation: "horizontal",
            widgets: [{
                    name: "DynamicForm",
                    extras: {
                        formId: "55",
                        selfSubmitting: true
                    }
                }
            ]
        }, {
            page: "dashboard",
            location: "top",
            orientation: "horizontal",
            devices: ["tablet", "desktop", "phone"],
            widgets: [{
                    name: "FullBannerAd"
                }, {
                    name: 'DynamicForm',
                    devices: ['phone'],
                    extras: {
                        formId: 21,
                        selfSubmitting: true
                    }
                }
            ]
        }, {
            page: "dashboard",
            location: "bottom",
            orientation: "horizontal",
            devices: ["tablet", "desktop"],
            widgets: [{
                    name: "Tecton",
                    featureName: "WorkforceGo",
                    moduleName: "Widget"
                }, {
                    'name': 'ManualAccounts',
                    'devices': ['tablet', 'desktop', 'phone']
                }, {
                    name: "AccountSummary"
                }
            ]
        }, {
            page: "dashboard",
            location: "right",
            orientation: "vertical",
            widgets: [{
                    name: "Stacked",
                    widgets: [{
                            name: "Transfer"
                        }]
                }, {
                    name: 'Tecton',
                    featureName: 'MarketplaceStore',
                    moduleName: 'ComposableWidget'
                }, {
                    name: "MediumAd"
                }, {
                    name: "RDC"
                }, {
                    name: "DynamicForm",
                    extras: {
                        formId: 9,
                        selfSubmitting: true
                    }
                }, {
                    'name': 'Tecton',
                    'featureName': 'LoanDueReminder',
                    'moduleName': 'Main',
                    'devices': ['tablet', 'desktop', 'phone']
                }, {
                    name: "DynamicForm",
                    extras: {
                        formId: 12,
                        selfSubmitting: true
                    }
                }
            ]
        }, {
            page: "dashboard",
            location: "top",
            orientation: "horizontal",
            devices: ["tablet", "desktop"],
            widgets: [{
                    name: "BannerAd"
                }
            ]
        }, {
            page: "commercial",
            location: "right",
            orientation: "vertical",
            widgets: [{
                    name: "MediumAd",
                    gravity: "above"
                }
            ]
        }, {
            page: "template",
            location: "right",
            orientation: "vertical",
            widgets: [{
                    name: "MediumAd",
                    gravity: "above"
                }
            ]
        }, {
            page: "recipient",
            location: "right",
            orientation: "vertical",
            widgets: [{
                    name: "MediumAd",
                    gravity: "above"
                }
            ]
        }, {
            page: "transfer",
            location: "right",
            orientation: "vertical",
            widgets: [{
                    name: "MediumAd",
                    gravity: "above"
                }
            ]
        }, {
            page: "billpay",
            location: "right",
            orientation: "vertical",
            widgets: [{
                    name: "MediumAd",
                    gravity: "below"
                }
            ]
        }, {
            page: "accounts",
            location: "account-detail-middle",
            orientation: "horizontal",
            widgets: [{
                    name: "DynamicForm",
                    extras: {
                        formId: "55",
                        selfSubmitting: true
                    }
                }
            ]
        }, {
            page: "rdc",
            location: "right",
            orientation: "vertical",
            widgets: [{
                    name: "MediumAd",
                    gravity: "above"
                }
            ]
        }, {
            page: "branches",
            location: "right",
            orientation: "vertical",
            widgets: [{
                    name: "MediumAd",
                    gravity: "above"
                }
            ]
        }, {
            page: "messages",
            location: "top",
            orientation: "horizontal",
            devices: ["tablet", "desktop"],
            widgets: [{
                    name: "BannerAd"
                }
            ]
        }, {
            page: "messages",
            location: "right",
            orientation: "vertical",
            widgets: [{
                    name: "MediumAd",
                    gravity: "above"
                }
            ]
        }, {
            page: "settings",
            location: "right",
            orientation: "vertical",
            widgets: [{
                    name: "MediumAd",
                    gravity: "above"
                }
            ]
        }, {
            page: "approvals",
            location: "right",
            orientation: "vertical",
            widgets: [{
                    name: "MediumAd",
                    gravity: "above"
                }
            ]
        }
    ]
};
__exports__['default'] = themeJS;
});
