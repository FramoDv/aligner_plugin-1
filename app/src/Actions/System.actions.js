import SystemConstants from "../Constants/System.constants";
import {httpLogin, httpLogout, httpMe, httpRegistration} from "../HttpRequests/System.http";

let AppDispatcher = require('../Stores/AppDispatcher');

let SystemActions = {
    /**
     *
     * @param {Boolean} status The status of login, true for open the login and false for close
     */
    setLoginStatus: function (status) {
        AppDispatcher.dispatch({
            actionType: SystemConstants.OPEN_LOGIN,
            status: status
        });
    },

    /**
     *
     * @param {Boolean} status The status of confirm registration, true for open the login and false for close
     */
    setConfirmModalStatus: function (status) {
        AppDispatcher.dispatch({
            actionType: SystemConstants.OPEN_CONFIRM_REGISTRATION_MODAL,
            status: status
        });
    },

    /**
     *
     * @param {Boolean} status The status of registration
     */
    setRegistrationStatus: function (status) {
        AppDispatcher.dispatch({
            actionType: SystemConstants.OPEN_REGISTRATION_MODAL,
            status: status
        });
    },

    /**
     *
     * @param {Boolean} status The status of registration error
     */
    setRegistrationError: function (status) {
        AppDispatcher.dispatch({
            actionType: SystemConstants.REGISTRATION_ERROR,
            status: status
        });
    },


    /**
     *
     * @param {Boolean} status The status of Export Modal, true for open the modal and false for close
     */
    setExportModalStatus: function (status) {
        AppDispatcher.dispatch({
            actionType: SystemConstants.OPEN_EXPORT_MODAL,
            status: status
        });
    },

    /**
     *
     * @param {Boolean} status The status of Reset Password Modal, true for open the modal and false for close
     */
    setResetPasswordStatus: function (status) {
        AppDispatcher.dispatch({
            actionType: SystemConstants.OPEN_RESET_PASSWORD_MODAL,
            status: status
        });
    },

    /**
     *
     * @param {Boolean} status The status of Logout Modal, true for open the modal and false for close
     */
    setLogoutStatus: function (status) {
        AppDispatcher.dispatch({
            actionType: SystemConstants.LOGOUT,
            status: status
        });
    },


    /**
     *
     */
    checkUserStatus: function () {
        httpMe()
            .then(response => {
                AppDispatcher.dispatch({
                    actionType: SystemConstants.USER_STATUS,
                    status: response.data.user,
                    fromLogin: false
                })
            })
            .catch(error => {
                AppDispatcher.dispatch({
                    actionType: SystemConstants.USER_STATUS,
                    status: false,
                    fromLogin: false
                })
            })
    },

    /**
     *
     * @param data , an object with email and password
     */
    login: function (data) {
        httpLogin(data)
            .then(() => {
                httpMe().then(response => {
                    AppDispatcher.dispatch({
                        actionType: SystemConstants.USER_STATUS,
                        status: response.data.user,
                        fromLogin: true,
                    });
                })
            })
            .catch(error => {
                AppDispatcher.dispatch({
                    actionType: SystemConstants.USER_STATUS,
                    status: false,
                    fromLogin: false,
                    error: true
                })
            })
    },

    /**
     *
     * @param data , an object with registration field
     */
    registration: function (data) {
        httpRegistration(data)
            .then(response => {
                this.setRegistrationStatus(false);
                this.setConfirmModalStatus(true);
                //console.log(response);
            })
            .catch(error => {
                const err = error.response.data.error.message;
                //console.log(err);
                this.setRegistrationError(err);
            })
    },

    logout: function () {
        httpLogout()
            .then(response => {
                AppDispatcher.dispatch({
                    actionType: SystemConstants.USER_STATUS,
                    status: false,
                });
            })
            .catch(error => {

            })
    },

    setInDrag: function (status) {
        AppDispatcher.dispatch({
            actionType: SystemConstants.IN_DRAG,
            status: status
        });
    }

};


export default SystemActions;