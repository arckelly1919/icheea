(function() {
  /**
   * Generates or retrieves a unique session ID stored in the browser's localStorage.
   * This ensures the ID is "per site" and persists across refreshes.
   */
  var getSessionId = function() {
    var siteId = (window.__GliaIntegration && window.__GliaIntegration.site_id) || 'default';
    var key = 'glia_site_sid_' + siteId;
    var existingId = sessionStorage.getItem(key);
    
    if (!existingId) {
      existingId = crypto.randomUUID(); 
      sessionStorage.setItem(key, existingId);
    }

    return existingId;
  };

  var globalConfig = {
    waitForQ2Ready: {
      delayBetweenTries: 1000,
      maxAttempts: 5
    },
    onQ2Ready: {
      forceGliaInstallAfter: 3000
    },
    onGliaReady: {
      intervalDuration: 16
    },
    getQ2VisitorDataWithRetries: {
      maxRetries: 3,
      delayBetweenRetries: 3000
    },
    gSetVisitorInformation: {
      newVisitorIdIntervalDuration: 16,
      newVisitorIdUpdateRetriesAttempts: 500
    },
    logger: {
      logToConsole: false,
      consoleLogPrefix: '[FILTER] ',
      defaultPayload: {
        glia_team: 'sudo',
        origin: 'q2_glia_integration'
      }
    },
    installGlia: {
      scriptPath: 'https://api.glia.com/salemove_integration.js'
    },
    onAuthenticatedWithQ2: {
      // Check if current id_token is expired or about to expire every 1 second
      checkIdTokenIntervalMs: 1000
    },
    shouldRefreshIdToken: {
      // Refresh id_token when current token has less than 1 minute TTL left
      thresholdSeconds: 60
    },
    setGliaLocale:  {
      supportedLocales: ['en-US', 'de-DE', 'es-MX', 'et-EE', 'fr-CA']
    }
  };

  /**
   * PostMessage Listener for Iframe Synchronization
   * This listens for 'REQUEST_SESSION_ID' from iframes and replies with id,
   * which the iframe uses as a sessionId to initialize Glia under the same session as the parent page.
   */
  window.addEventListener('message', function(event) {
    if (event.data.type === 'REQUEST_SESSION_ID') {
      event.source.postMessage({
        type: 'SESSION_ID_RESPONSE',
        sessionId: getSessionId(),
        idToken: (window.getGliaContext ? window.getGliaContext().idToken : null) || null,
      }, event.origin);
    }
  });

  /**
   * The reason why it's done in such a manner is because we need to be able to tell
   * if the user is authenticated or not. It's possible only when Q2 is fully loaded
   * and when its Frontend successfully confirmed authentication status with its
   * Backend. Sometimes it takes more than one attempt for Q2 Frontend to get a
   * confirmation. Without it - we can't make authenticated requests to our Backend
   * extension
   *
   * There's no way to determine whether a different Frontend app which runs in parallel
   * and does its own logic has finished it via standard APIs we have access to.
   *
   * Q2 runs Ember app and it seems that it's also not possible to effectively hack into
   * app lifecycle and determine whether all redirects have happened, all hooks have been
   * executed and so on.
   *
   * But even we could do so - having a simpler approach with timeout and retry seems to
   * be more stable.
   */

  if (document.readyState === 'complete') {
    onDocumentReady();
  } else {
    window.addEventListener('load', onDocumentReady);
  }

  function onDocumentReady() {
    waitForQ2Ready(onQ2Ready);
  }

  var waitForQ2Ready = (function makeWaitForQ2Ready() {
    var callbacks = [];
    var delayBetweenTries = globalConfig.waitForQ2Ready.delayBetweenTries;
    var maxAttempts = globalConfig.waitForQ2Ready.maxAttempts;
    var isReady = false;

    return function waitForQ2Ready(callback = noop) {
      if (isReady) {
        setTimeout(function() {
          callback(null, getQ2Controllers());
        }, 0);
        return;
      }

      callbacks.push(callback);

      var currentAttempt = 0;

      var intervalId = setInterval(function() {
        var controllers = getQ2Controllers();
        if (controllers && controllers.notificationCenter != null) {
          isReady = true;

          clearInterval(intervalId);
          logger.info('Q2 controllers detected, Q2 ready.');
          callbacks.forEach(function(callback) {
            callback(null, controllers);
          });

          callbacks.splice(0);
        } else if (++currentAttempt >= maxAttempts) {
          clearInterval(intervalId);

          var error = new Error('Max retries while waiting for Q2 ready has been reached.');
          logger.warn('Q2 controllers were not detected, max Retries reached.', {
            err: error
          });

          callbacks.forEach(function(callback) {
            callback(error);
          });

          callbacks.splice(0);
        } else {
          logger.warn('Q2 controllers were not detected, Q2 not ready yet, retrying.');
        }
      }, delayBetweenTries);
    }
  })();

  function getQ2Controllers() {
    if (!window.Ngam) {
      return null;
    }

    var applicationController = Ngam.__container__.lookup('controller:application');
    var notificationCenter = applicationController && applicationController.get('notificationCenter');

    if (!notificationCenter) {
      return null;
    }

    var loginFlow = Ngam.__container__.lookup('service:loginFlow');
    var loginController = Ngam.__container__.lookup('controller:login');
    var loginFlowReq = loginController && loginController.get('loginFlowRequirements');

    return {
      loginFlow: loginFlow,
      loginFlowRequirements: loginFlowReq,
      notificationCenter: notificationCenter
    };
  }

  function onQ2Ready(error, controllers) {
    if (error) {
      logger.warn(
        "Q2 script never returned controllers, visitor authentication feature is disabled.",
        {err: error}
      );

      installGlia(function() {
        logger.warn('Glia is installed with disabled visitor authentication.');
      });

      return;
    }

    if (isAuthenticatedWithQ2()) {
      logger.info(
        "Authentication from Q2 detected from page reload, trying to authenticate with Glia."
      );
      visitorQ2Attributes.loginVisitor();
      onAuthenticatedWithQ2();
    }

    listenToAuthHooks(controllers.notificationCenter);

    setTimeout(function() {
      if (window.sm != null) return;

      if (visitorQ2Attributes.hasEverLoggedIn()) {
        logger.info(
          "Glia has not been installed after: " + globalConfig.onQ2Ready.forceGliaInstallAfter + "ms. But there is a pending log-in occurring."
        );
      } else {
        logger.info(
          "Glia has not been installed after: " + globalConfig.onQ2Ready.forceGliaInstallAfter + "ms. No pending Log-in found. Installing Glia."
        );
        installGlia();
      }
    }, globalConfig.onQ2Ready.forceGliaInstallAfter);
  }

  function isAuthenticatedWithQ2() {
    var controllers = getQ2Controllers();

    var loginFlowSuccess = !!(controllers.loginFlow && controllers.loginFlow.authenticatedStatus === 200);
    var loginFlowReqSuccess = !!(controllers.loginFlowRequirements && controllers.loginFlowRequirements.authenticatedStatus === 200);

    // ".userId === 0" -> Secure Access Code workflow related check
    if (loginFlowSuccess) {
      return controllers.loginFlow.userId !== 0;
    } else if (loginFlowReqSuccess) {
      return controllers.loginFlowRequirements.userId !== 0;
    } else {
      return false;
    }
  }

  var onAuthenticatedWithQ2 = (function makeOnAuthenticationWithQ2() {
    var currentCallId = -1;

    return function onAuthenticatedWithQ2() {
      var startedAtCallId = ++currentCallId;

      getQ2VisitorDataWithRetries(function(error, data) {
        if (error) {
          logger.warn(
            "Q2 couldn't return valid id token.",
            {err: error}
          );

          installGlia();

          return;
        }

        updateGliaContext({
          idToken: data.idToken,
          accessToken: data.accessToken
        });

        installGlia();

        onGliaReady(function(gliaApi) {
          if (startedAtCallId !== currentCallId) return;

          gSetVisitorInformation(data, gliaApi);

          if (data.settings.force_user_locale && data.settings.user_locale) {
            setGliaLocale(data.settings.user_locale, gliaApi);
          }
        });

        if (data.idToken) {
          var idTokenExpMs = getJwtExpMS(data.idToken);
          var idTokenExpiresAt = new Date(idTokenExpMs);

          var intervalId = setInterval(function() {
            if (startedAtCallId !== currentCallId || !visitorQ2Attributes.isAuthenticated()) {
              clearInterval(intervalId);
            } else if (shouldRefreshIdToken(idTokenExpiresAt)) {
              clearInterval(intervalId);
              logger.info('Refreshing visitor id_token via Q2 user_data endpoint.');
              onAuthenticatedWithQ2();
            }
          }, globalConfig.onAuthenticatedWithQ2.checkIdTokenIntervalMs);
        } else {
          logger.info('user_data endpoint response did not include id_token.', {q2_user_id: data.user.auser_id});
        }
      });
    }
  })();

  function setGliaLocale(localeKey, gliaApi) {
    var isLocaleSupportedByGlia = globalConfig.setGliaLocale.supportedLocales.indexOf(localeKey) !== -1;

    if (!isLocaleSupportedByGlia) {
      logger.warn('Attempted to set user locale but unsupported localeKey "' + localeKey + '" provided');

      return;
    }

    logger.info('Setting user locale to ' + localeKey);
    gliaApi.setLocale(localeKey);
  }

  function shouldRefreshIdToken(idTokenExpiresAt) {
    var now = new Date();
    var idTokenExpiresInSec = (idTokenExpiresAt - now) / 1000;

    return idTokenExpiresInSec < globalConfig.shouldRefreshIdToken.thresholdSeconds;
  }

  function listenToAuthHooks(notificationCenter) {
    notificationCenter.on('POST_LOGIN', function() {
      if (visitorQ2Attributes.isAuthenticated()) {
        logger.warn(
          "POST_LOGIN fired when visitor is already authenticated."
        );
      } else {
        logger.info(
          "Authentication from Q2 detected from watcher, trying to authenticate with Glia."
        );
        visitorQ2Attributes.loginVisitor();
        onAuthenticatedWithQ2();
      }
    });

    notificationCenter.on('BEFORE_LOGOFF', function() {
      logger.info(
        "Unauthentication from Q2 detected from watcher, trying to unauthenticate with Glia."
      );
      updateGliaContext({idToken: null, accessToken: null});
      visitorQ2Attributes.logoffVisitor();
    });

    logger.info(
      "Watching for visitor Login and Logoff in Q2."
    );
  }

  var getQ2VisitorDataWithRetries = (function makeGetQ2VisitorDataWithRetries() {
    var currentCallId = -1;
    var maxRetries = globalConfig.getQ2VisitorDataWithRetries.maxRetries;
    var delayBetweenRetries = globalConfig.getQ2VisitorDataWithRetries.delayBetweenRetries;

    return function getQ2VisitorDataWithRetries(callback = noop) {
      var startedAtCallId = ++currentCallId;
      var currentAttempt = 0;

      function retryFunction() {
        if (currentAttempt >= maxRetries) {
          callback(new Error('Max Retries to reach Q2 ID Token API has reached.'));
          return;
        }

        if (startedAtCallId !== currentCallId) {
          logger.warn('Cancel getQ2VisitorDataWithRetries with call_id: ' + startedAtCallId + '". Due function being called again.');
          callback(new Error('getQ2VisitorDataWithRetries cancelled.'));
          return;
        }

        if (!visitorQ2Attributes.isAuthenticated()) {
          logger.warn('Visitor Unauthenticated while trying to reach user_data endpoint.');
          callback(new Error('Visitor has unauthenticated during reaching Q2 retries.'));
          return;
        }

        getQ2VisitorData(function(error, data) {
          if (error) {
            currentAttempt += 1;
            setTimeout(function() {
              retryFunction();
            }, delayBetweenRetries);
            return;
          }

          if (!visitorQ2Attributes.isAuthenticated()) {
            logger.warn('Visitor Unauthenticated while trying to reach user_data endpoint.');
            callback(new Error('Visitor has unauthenticated during reaching Q2 retries.'));
            return;
          }

          logger.info('Visitor user_data endpoint has been fetched successfully.');
          callback(null, data);
        });
      }

      retryFunction();
    }
  })();

  function getQ2VisitorData(callback = noop) {
    wedgeIntegrationController.get('store').sendPostRequest(JSON.stringify({
      formData: 'routing_key=user_data'
    }), 'mobilews/form/GliaIntegration', function(response) {
      var data = response.data;
      var response = null;

      try {
        response = JSON.parse(data.forms[0]);
      } catch(e) {
        var error = new Error('Failed to JSON.parse response from Q2.');
        logger.warn('Failed to fetch visitor user_data endpoint.', {err: error});
        callback(error);
        return;
      }

      callback(null, {
        idToken: response.data.id_token,
        user: response.data.user,
        accessToken: response.data.access_token,
        account: response.data.account,
        settings: response.data.settings
      });
    }, function(response) {
      var error = new Error('Request to Q2 returned with unsuccessful status code');
      logger.warn('Failed to fetch visitor user_data endpoint. Request info: ' + JSON.stringify(response), {
        err: error
      });
      callback(error);
    })
  }

  var gSetVisitorInformation = (function makeGSetVisitorInformation() {
    var currentCallId = -1;
    var maxAttempts = globalConfig.gSetVisitorInformation.newVisitorIdUpdateRetriesAttempts;

    return function gSetVisitorInformation(q2data, gliaApi) {
      var user = q2data.user;
      var account = q2data.account;
      var startedAtCallId = ++currentCallId;

      var customAttributes = prepareCustomAttributes(user, account);

      // Update information for current visitor_id instantly.
      updateInformation(gliaApi, user, customAttributes);

      var isDirectIdAuthentication = q2data.idToken != null;

      if (isDirectIdAuthentication) {
        // In case Direct ID authentication was initiated (by returning id_token from getGliaContext function),
        // there will be a delay before visitor will be authenticated on Glia's side.
        // Reason for the delay is that we have JS timer setup in visitor-js-api which polls getGliaContext function
        // every 1 sec (at the time of writing). If a new id_token is detected, request to backend is sent and
        // id_token is verified. Only after visitor-js-api receives successful verification response, we consider
        // visitor authenticated.
        // On authentication, Glia's visitor_id will change.

        // We need to delay updating visitor information here until Glia's visitor_id changes.
        // Otherwise visitor information would not be updated for the authenticated visitor_id.
        var initialVisitorId = gliaApi.getVisitorId();
        var attempt = 0;
        logger.info('Watching for the new visitor_id after Q2 authentication.');

        var intervalId = setInterval(function() {
          if (++attempt >= maxAttempts) {
            clearInterval(intervalId);
            logger.info('Stop Watching for visitor_id after authentication due max attempts.');
            return;
          } else if (startedAtCallId !== currentCallId) {
            clearInterval(intervalId);
            logger.info('Stop Watching for visitor_id after authentication due new visitor information arriving.');
            return;
          }

          var currentVisitorId = gliaApi.getVisitorId();

          if (initialVisitorId !== currentVisitorId) {
            clearInterval(intervalId);
            logger.info('New visitor_id after authentication with Q2 found, stop watching.');
            updateInformation(gliaApi, user, customAttributes);
          }
        }, globalConfig.gSetVisitorInformation.newVisitorIdIntervalDuration);
      }
    }
  })();

  function prepareCustomAttributes(user, account) {
    var replace = function(object) {
      Object.keys(object)
        .forEach(function(key) {
          if (object[key]) {
            typeof object[key] == 'object' ? replace(object[key]) : object[key]= String(object[key]);
          } else {
            object[key] = '';
          }
        });
    }
    var strUser = replace(user);
    var strAccount = replace(account);
    var attributes = Object.assign({}, user, account);
    delete attributes.external_id;
    return attributes;
  }

  var updateInformation = (function makeUpdateInformation() {
    var currentCallId = -1;

    return function updateInformation(gliaApi, user, customAttributes) {
      var startedAtCallId = ++currentCallId;

      var visitorInformation = {
        name: user.first_name + ' ' + user.last_name,
        customAttributesUpdateMethod: 'merge',
        customAttributes: customAttributes,
        externalId: user.external_id
      }

      gliaApi.updateInformation(visitorInformation)
      .then(function() {
        // No need to check for callId since it was sucessfully updated
        logCustomAttributes(customAttributes);
      })
      .catch(function(error) {
        if (startedAtCallId !== currentCallId) {
          return;
        }

        var gliaErrors = gliaApi.ERRORS;

        if ([
          gliaErrors.NETWORK_TIMEOUT,
          gliaErrors.CONNECTION_LOST
        ].includes(error.cause)) {
          logger.warn('updateInformation call failed. Retrying.', {err: error});
          updateInformation(gliaApi, user, customAttributes);
          return;
        }

        logger.warn('updateInformation call failed.', {err: error});
      });
    }
  })();

  function logCustomAttributes(customAttributes) {
    var formattedCustomAttributes = Object.entries(customAttributes)
      .reduce(function(acc, cur) {
        var key = cur[0];
        var value = cur[1];

        if (value) {
          acc.attributes.push(key);
        } else {
          acc.blank_attributes.push(key);
        }

        return acc;
      }, {attributes: [], blank_attributes: []});

    // attributes and blank_attributes .toString to avoid the list being pruned in kibana
    logger.info('Visitor information updated via `gliaApi.updateInformation`.', {
      attributes: formattedCustomAttributes.attributes.toString(),
      blank_attributes: formattedCustomAttributes.blank_attributes.toString(),
      q2_user_id: customAttributes.auser_id
    });
  }

  var logger = (function makeLogger() {
    function log(loggerFnName, message, payload) {
      onGliaReady(function() {
        var defaultPayload = globalConfig.logger.defaultPayload;
        var logToConsole = globalConfig.logger.logToConsole;
        var consoleLogPrefix = globalConfig.logger.consoleLogPrefix;

        var loggerFn = sm.logger[loggerFnName];
        if (loggerFn == null) {
          logger.error('Logger has been called with unexisting function: "'+ loggerFnName +'".');
          return;
        }

        var finalPayload = Object.assign({}, defaultPayload, payload);
        loggerFn(message, finalPayload);

        if (logToConsole) {
          console.log(consoleLogPrefix + message, finalPayload);
        }
      });
    }

    return {
      info: function info(message, payload = {}) {
        log('info', message, payload);
      },
      warn: function warn(message, payload = {}) {
        log('warn', message, payload);
      },
      error: function error(message, payload = {}) {
        log('error', message, payload);
      }
    };
  })();

  function updateGliaContext(newData) {
    var previousGliaContext = {};

    if (window.getGliaContext && typeof window.getGliaContext === 'function') {
      previousGliaContext = window.getGliaContext();
    }

    var contextData = Object.assign({}, previousGliaContext, {
      idToken: newData.idToken,
      accessToken: newData.accessToken,
      sessionId: getSessionId()
    });

    window.getGliaContext = function getGliaContext() {
      return contextData;
    }
  }

  var visitorQ2Attributes = (function visitorQ2Attributes() {
    var isVisitorAuthenticated = false;
    var loginCount = 0;

    return {
      loginVisitor: function loginVisitor() {
        isVisitorAuthenticated = true;
        loginCount++;
      },
      logoffVisitor: function logoffVisitor() {
        isVisitorAuthenticated = false;
      },
      isAuthenticated: function isAuthenticated() {
        return isVisitorAuthenticated;
      },
      hasEverLoggedIn: function hasEverLoggedIn() {
        return loginCount > 0;
      }
    }
  })();

  var onGliaReady = (function makeOnGliaReady() {
    var pendingCallbacks = [];
    var gliaReadyPromise = null;

    return function onGliaReady(callback) {
      if (gliaReadyPromise != null) {
        gliaReadyPromise.then(function(gliaApi) {
          callback(gliaApi);
        });
      } else {
        if (pendingCallbacks.length === 0) {
          var intervalId = setInterval(function() {
            if (window.sm == null) return;

            clearInterval(intervalId);

            gliaReadyPromise = window.sm.getApi();

            gliaReadyPromise
              .then(function(gliaApi) {
                pendingCallbacks.forEach(function(callback) {
                  callback(gliaApi);
                });

                pendingCallbacks.splice(0);
              });
          }, globalConfig.onGliaReady.intervalDuration);
        }

        pendingCallbacks.push(callback);
      }
    }
  })();

  var installGlia = (function makeInstallGlia() {
    var installGliaPendingCallbacks = [];

    return function installGlia(callback = function noop() {}) {
      if (window.sm) {
        setTimeout(function() {
          callback();
        }, 0);
        return;
      } else if (typeof(callback) === 'function') {
        installGliaPendingCallbacks.push(callback);
      }

      var gliaIntegrationScriptUrl = globalConfig.installGlia.scriptPath;

      if (window.__GliaIntegration && window.__GliaIntegration.site_id) {
        try {
          if (window.__GliaIntegration.force_site_id) {
            gliaIntegrationScriptUrl = gliaIntegrationScriptUrl + "?site_id=" + window.__GliaIntegration.site_id;
          } else {
            window.top.origin; // if the domains are different then cross origin blocking will throw an exception and hence needs site_id appended.
          }
        } catch (e) {
          gliaIntegrationScriptUrl = gliaIntegrationScriptUrl + "?site_id=" + window.__GliaIntegration.site_id;
        }
      }

      var scriptElement = document.createElement('script');
      scriptElement.async = 1;
      scriptElement.src = gliaIntegrationScriptUrl;
      scriptElement.type = 'text/javascript';

      scriptElement.addEventListener('load', function() {
        logger.info('Glia Script has been loaded.');
        installGliaPendingCallbacks.forEach(function(callback) {
          callback.apply(null, arguments);
        });
        installGliaPendingCallbacks.splice(0);
      });

      scriptElement.addEventListener('error', function() {
        logger.warn('Glia Script failed to load.');
        installGliaPendingCallbacks.forEach(function(callback) {
          callback.apply(null, arguments);
        });
        installGliaPendingCallbacks.splice(0);
      });

      document.body.append(scriptElement);
    }
  })();

  function noop() {}

  var parseJwt = function(jwt) {
    const base64 = jwt.split('.')[1];
    const payload = decodeURIComponent(
      atob(base64)
        .split('')
        .map(c => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2))
        .join('')
    );
    return JSON.parse(payload);
  };

  var getJwtExpMS = function(jwt) {
    return parseJwt(jwt).exp * 1000;
  };
})();
