import React, {Component} from 'react';
import { Link } from "react-router-dom";
import PropTypes from "prop-types";
import {textEllipsisCenter} from "../../../Helpers/SystemUtils.helper";
import User from "./User/User.component";
import Mismatch from "./Mismatch/Mismatch.component";
import Export from "./Export/Export.component";
import ToolbarComponent from "../../Project/Toolbar/Toolbar.component";
import {httpGetAlignmentInfo} from "../../../HttpRequests/Alignment.http";

class HeaderComponent extends Component {

    static propTypes = {
        hideToolbar: PropTypes.bool,
        match: PropTypes.shape({
            params: PropTypes.shape({
                jobID: PropTypes.string,
                jobPassword: PropTypes.string
            })
        }).isRequired,
        user: PropTypes.oneOfType([PropTypes.bool,PropTypes.object]),
        image: PropTypes.string
    };

    constructor(props) {
        super(props);
/*        const jobID = (this.props.match
            && this.props.match.params
            && this.props.match.params.jobID) ? this.props.match.params.jobID : null;*/
        this.state = {
            pName: '',
            projectTitle: '',
            sourceLang: '',
            targetLang: '',
            job: {
                config: {
                    password: this.props.match.params.password,
                    id: this.props.match.params.jobID
                },
                name: null,
                segments: null
            },
            loggedIn: false,
        };
    }

    static getDerivedStateFromProps(nextProps, prevState) {
         if(nextProps.match.params && nextProps.match.params.jobID){
             prevState.job.config.id = nextProps.match.params.jobID;
             prevState.job.config.password = nextProps.match.params.jobPassword;
         }else{
             prevState.job.config.id = null;
             prevState.job.config.password = null;
         }
        return prevState;
    };

    componentDidMount(){
        this.getInfo();
    }

    componentDidUpdate =(prevProps) => {
        // Typical usage (don't forget to compare props):
        if (this.props.match.params.jobID !== prevProps.match.params.jobID) {
            this.getInfo();
        }
    }

    renderHtmlNavigation = () => {

        if(this.state.job.config.id){
            return <div>
                <ul className="aligner-nav-log" role="navigation">
                    <li>
                        <Link to="/">
                            <div id="logo"></div>
                        </Link>
                    </li>
                    <li></li>
                    <li>
                        <div id="final_title">
                            {this.state.projectTitle}
                        </div>
                    </li>

                    <li id={"source"}>
                        <span>{this.state.sourceLang}</span>
                    </li>

                    <li id={"to"}>
                        <span> > </span>
                    </li>

                    <li id={"target"}>
                        <span>{this.state.targetLang}</span>
                    </li>

                    <li id="export">
                        {/*<Mismatch />*/}
                        <Export/>
                    </li>

                    <li>
                        <User image={this.props.image} user={this.props.user}/>
                    </li>
                </ul>
                {!this.props.hideToolbar && <ToolbarComponent jobConf={this.state.job.config}/>}
            </div>;
        } else {
            return <ul className="aligner-nav-nolog" role="navigation">
                <li>
                    <Link to="/">
                        <div id="logo"></div>
                    </Link>
                </li>
                <li>
                    <User image={this.props.image} user={this.props.user}/>
                </li>
            </ul>
        }
    };

    render() {
        return (
            <div id="header">
                { this.renderHtmlNavigation() }
            </div>
        );
    }

    getInfo = () => {
        // get job info
        httpGetAlignmentInfo(this.state.job.config.id, this.state.job.config.password)
            .then(
                response => {
                    const info = response.data;
                    this.setState({
                        projectTitle: textEllipsisCenter(info.job_name),
                        sourceLang: info.source_lang,
                        targetLang: info.target_lang,
                    });
                }

            ).catch(
            error => {
                console.log(error);
            }
        );
    }

}
export default HeaderComponent;
