import React, {Component} from 'react';
import PropTypes from "prop-types";
import {Dropdown} from 'semantic-ui-react'
import Dropzone from 'react-dropzone'
import env from '../../../Constants/Env.constants'
import {httpUpload} from '../../../HttpRequests/Upload.http';
import {httpConversion, httpCreateProject, httpAlignJob} from "../../../HttpRequests/Alignment.http";
import {Redirect} from "react-router";


class UploadComponent extends Component {
    static propTypes = {};

    constructor() {
        super();
        let languages = [];
        env.languages.map(e => {
            languages.push({
                key: e.code,
                text: e.value,
                value: e.code
            })
        });
        this.state = {
            languages: languages,
            job: undefined,
            pName: '',
            fileNameSource: null,
            fileNameTarget: null,
            uploadTarget: {
                status: 0,
                start: false
            },
            sourceLang: 'en-US',
            targetLang: 'it-IT'
        }
    }


    onSourceLanguageChange = (e, value) => {
        this.setState({
            sourceLang: value.value
        })
    };

    onTargetLanguageChange = (e, value) => {
        this.setState({
            targetLang: value.value
        })
    };

    ProjectNameChange = (event) => {
        this.setState({pName: event.target.value});
    };

    onDropSource = (files) => {
        const onProgress = progressEvent => null;
        httpUpload(files[0], onProgress).then(response => {
            if (!response.errors) {
                httpConversion({
                    file_name: response.data.file.name,
                    source_lang: this.state.sourceLang,
                    target_lang: this.state.targetLang
                });
                this.setState({
                    fileNameSource: response.data.file.name
                });
            }
        });
    };


    onDropTarget = (files) => {
        const onProgress = progressEvent => {
            this.setState({
                uploadTarget: {
                    status: 0,
                    start: true
                }
            })
        };
        httpUpload(files[0], onProgress).then(response => {

            if (!response.errors) {
                httpConversion({
                    file_name: response.data.file.name,
                    source_lang: this.state.targetLang,
                    target_lang: this.state.sourceLang
                });
                this.setState({
                    fileNameTarget: response.data.file.name
                });
            }
        });
    };

    startAlignment = () => {
        httpCreateProject({
            project_name: this.state.pName,
            file_name_source: this.state.fileNameSource,
            file_name_target: this.state.fileNameTarget,
            source_lang: this.state.sourceLang,
            target_lang: this.state.targetLang
        }).then(response => {

            this.setState({
                job: {
                    id: response.data.job.id,
                    password: response.data.job.password
                }
            });

            /* httpAlignJob(response.data.job.id).then(response => {
                 if(response.data){

                 }
                 console.log(response)
             })*/
        })
    };

    render() {

        const uploadAreaStyle = {};

        if (this.state.job) {
            return <Redirect push to={'/project/' + this.state.job.id + '/' + this.state.job.password}/>;
        }

        return (
            <div className="uploadComponent">
                <div className="uploadCard ui grid">
                    <div className="row" id="projectNameHeader">
                        <h3 className="ui header">Alignment project name <span>(optional)</span></h3>
                    </div>

                    <div className="row" id="projectNameInput">
                        <div className="fourteen wide column">
                            <div className="ui input">
                                <input className="form-control" name="pname" type="text" value={this.state.pName}
                                       onChange={this.ProjectNameChange}/>
                            </div>
                        </div>

                        <div className="two wide column">
                            <p>
                                <i aria-hidden='true' className='setting icon'/>
                                <span>Settings</span>
                            </p>
                        </div>
                    </div>

                    <div className="row">
                        <div className="six wide column">
                            <Dropdown fluid search selection
                                      options={this.state.languages}
                                      defaultValue={this.state.sourceLang}
                                      onChange={this.onSourceLanguageChange}
                            />
                        </div>
                        <div className="ten wide column">
                            <div className="dropzone">
                                <Dropzone style={uploadAreaStyle} onDrop={this.onDropSource}>
                                    <p><span>+ Add source file</span> (or drop it here).</p>
                                </Dropzone>
                            </div>
                        </div>
                    </div>
                    <div className="row">
                        <div className="six wide column">
                            <Dropdown fluid search selection
                                      options={this.state.languages}
                                      defaultValue={this.state.targetLang}
                                      onChange={this.onTargetLanguageChange}
                            />
                        </div>
                        <div className="ten wide column">
                            <div className="dropzone">
                                <Dropzone style={uploadAreaStyle} onDrop={this.onDropTarget}>
                                    <p><span>+ Add source file</span> (or drop it here).</p>
                                </Dropzone>
                            </div>
                        </div>
                    </div>
                    <div className="row" id="buttonRow">

                        <div className="twelve wide column">
                            <h4>MateCat supports <span> 71 file formats </span></h4>
                        </div>

                        <div className="four wide column">
                            <button className="ui primary button" onClick={this.startAlignment}
                                    disabled={!this.state.fileNameSource || !this.state.fileNameTarget}
                            >Start alignment
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        );
    }
}

export default UploadComponent;