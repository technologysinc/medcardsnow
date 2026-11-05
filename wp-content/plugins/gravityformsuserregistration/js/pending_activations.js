window.gfPendingActivationAction = function( element ) {
    var action = element.dataset.activationAction;
    var key    = element.dataset.activationKey;
    singleItemAction( action, key );
};