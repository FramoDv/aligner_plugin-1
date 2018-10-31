import React, {Component} from 'react';
import {Route} from 'react-router-dom'
import HeaderComponent from "../Header/Header.component";
import LoginComponent from "../Login/Login.component";
import SystemConstants from "../../../Constants/System.constants";
import SystemStore from "../../../Stores/System.store";
import ExportModal from "../ExportModal/ExportModal.component";
import ResetPasswordModal from "../ResetPasswordModal/ResetPasswordModal.component";
import SystemActions from "../../../Actions/System.actions";
import LogoutComponent from "../Logout/Logout.component";
import RegistrationComponent from "../Registration/Registration.component";
import ConfirmRegistrationComponent from "../ConfirmRegistration/ConfirmRegistration.component";

class Layout extends Component {
    constructor(props) {
        super(props);
        this.state = {
            statusLogin: false,
            statusExportModal: false,
            statusResetPasswordModal: false,
            statusLogout: false,
            statusRegistrationModal: false,
            statusConfirmRegistrationModal: false,
            user: false,
            loginError: false,
            newUserEmail: '',
            registrationError: ''
        }
    }

    componentDidMount() {
        SystemActions.checkUserStatus();
        SystemStore.addListener(SystemConstants.USER_STATUS, this.userStatus);
        SystemStore.addListener(SystemConstants.REGISTRATION_ERROR, this.setRegistrationError);
        SystemStore.addListener(SystemConstants.LOGOUT, this.setLogoutStatus);
        SystemStore.addListener(SystemConstants.OPEN_REGISTRATION_MODAL, this.setStatusRegistration);
        SystemStore.addListener(SystemConstants.OPEN_CONFIRM_REGISTRATION_MODAL, this.setStatusRegistrationCompleted);
        SystemStore.addListener(SystemConstants.OPEN_LOGIN, this.setStatusLogin);
        SystemStore.addListener(SystemConstants.OPEN_EXPORT_MODAL, this.setStatusExportModal);
        SystemStore.addListener(SystemConstants.OPEN_RESET_PASSWORD_MODAL, this.setStatusResetPasswordModal);
    }

    componentWillUnmount() {
        SystemStore.removeListener(SystemConstants.USER_STATUS, this.userStatus);
        SystemStore.addListener(SystemConstants.REGISTRATION_ERROR, this.setRegistrationError);
        SystemStore.removeListener(SystemConstants.LOGOUT, this.setLogoutStatus);
        SystemStore.removeListener(SystemConstants.OPEN_REGISTRATION_MODAL, this.setStatusRegistration);
        SystemStore.removeListener(SystemConstants.OPEN_CONFIRM_REGISTRATION_MODAL, this.setStatusRegistrationCompleted);
        SystemStore.removeListener(SystemConstants.OPEN_LOGIN, this.setStatusLogin);
        SystemStore.removeListener(SystemConstants.OPEN_EXPORT_MODAL, this.setStatusExportModal);
        SystemStore.removeListener(SystemConstants.OPEN_RESET_PASSWORD_MODAL, this.setStatusResetPasswordModal);
    }

    render = () => {
        const {component: Component, ...rest} = this.props;
        return <Route {...rest} render={matchProps => (
            <div className="DefaultLayout">
                {this.state.statusConfirmRegistrationModal && <ConfirmRegistrationComponent email={this.state.newUserEmail}/>}
                {this.state.statusRegistrationModal && <RegistrationComponent error={this.state.registrationError}/>}
                {this.state.statusResetPasswordModal && <ResetPasswordModal />}
                {this.state.statusLogin && < LoginComponent error = {this.state.loginError}/>}
                {this.state.statusExportModal && <ExportModal user = {this.state.user} error = {this.state.loginError}/>}
                {this.state.statusLogout && < LogoutComponent user = {this.state.user}/>}
                <HeaderComponent user = {this.state.user} {...rest} {...matchProps}/>
                <Component {...matchProps} />
                <div id="hiddenHtml"></div>
            </div>
        )}/>
    };

    setStatusLogin = (status) => {
        this.setState({
            statusLogin: status
        })
    };

    setRegistrationError = (status) => {
        this.setState({
            registrationError: status
        })
    };

    setStatusRegistration = (status) => {
        this.setState({
            statusRegistrationModal: status
        })
    };

    setStatusRegistrationCompleted = (status, email) => {
        this.setState({
            statusConfirmRegistrationModal: status,
            newUserEmail: email
        })
    };

    setStatusExportModal = (status) => {
        this.setState({
            statusExportModal: status
        })
    };

    setStatusResetPasswordModal = (status) => {
        this.setState({
            statusResetPasswordModal: status
        })
    };

    setLogoutStatus = (status) => {
        this.setState({
            statusLogout: status
        })
    };

    userStatus = (status,fromLogin, error) => {
        if(status && fromLogin && !error){
            setTimeout(()=>{
                SystemActions.setLoginStatus(false);
            },0);
            this.setState({
                loginError: false
            })
        }
        if(error){
            this.setState({
                loginError: true
            })
        }
        this.setState({
            user: status
        })
    };
}


/*const Layout = ({component: Component, ...rest}) => {

    return (
        <Route {...rest} render={matchProps => (
            <div className="DefaultLayout">
                <HeaderComponent {...matchProps}/>
                <Component {...matchProps} />
                <FooterComponent {...matchProps}/>
            </div>
        )} />
    )
};*/

export default Layout;
