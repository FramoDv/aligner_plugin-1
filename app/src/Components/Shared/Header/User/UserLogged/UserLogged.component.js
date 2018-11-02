import React, {Component} from 'react';
import SystemActions from "../../../../../Actions/System.actions";
import PropTypes from "prop-types";
import {getUserInitials} from "../../../../../Helpers/SystemUtils.helper";

class UserLogged extends Component {

    static propTypes = {
        user: PropTypes.oneOfType([PropTypes.bool,PropTypes.object]),
        image: PropTypes.string
    };

    constructor(props) {
        super(props);
    }

    render() {
        return (
            <div>
                <div className="ui logged label" title="Login" onClick={this.openLogout}>
                    { this.props.image ?
                        <img src={this.props.image}/> : null
                    }
                    {!this.props.image && getUserInitials(this.props.user.first_name, this.props.user.last_name )}
                </div>
            </div>
        );
    }

    openLogout = () =>{
       SystemActions.setLogoutStatus(true);
    };

}
export default UserLogged;