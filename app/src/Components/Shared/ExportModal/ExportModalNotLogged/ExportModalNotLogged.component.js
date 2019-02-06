import React, {Component} from 'react';
import PropTypes from "prop-types";
import LoginComponent from "../../Login/Login.component";

class ExportModalNotLogged extends Component {

    static propTypes = {
        user: PropTypes.oneOfType([PropTypes.bool, PropTypes.object]),
        googleLink: PropTypes.string
    };

    constructor(props) {
        super(props);
    }

    render() {
        return (
            <div>
                <h1>Please log in</h1>
                <h3>Use your credentials or sign in with Google</h3>
                <LoginComponent error={this.props.error} googleLink={this.props.googleLink} fromExport={true}/>
            </div>
        );
    }
}

export default ExportModalNotLogged;
