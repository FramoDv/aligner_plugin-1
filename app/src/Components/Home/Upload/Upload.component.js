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
            fileSizeSource: 0,
            uploadSource: {
                progress: 0,
                start: false,
                status: 'start'
            },
            fileNameTarget: null,
            fileSizeTarget: 0,
            uploadTarget: {
                progress: 0,
                start: false,
                status: 'start'
            },
            sourceLang: 'en-US',
            targetLang: 'it-IT',
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
        const onProgress = progressEvent => {
            this.setState({
                fileSizeSource: Math.floor((progressEvent.total) / 1000),
                fileNameSource: files[0].name,
                uploadSource: {
                    progress: ((progressEvent.loaded * 100) / progressEvent.total),
                    start: true,
                    status: 'progress',
                }
            });
        };
        httpUpload(files[0], onProgress).then(response => {
            if (!response.errors) {
                httpConversion({
                    file_name: response.data[0].name,
                    source_lang: this.state.sourceLang,
                    target_lang: this.state.targetLang
                });
                this.setState({
                    fileNameSource: response.data[0].name,
                    uploadSource: {
                        status: 'finish',
                        progress: 0
                    }

                });
            }
        }, (error) => {
            this.setState({
                uploadSource: {
                    progress: 0,
                    status: 'error'
                }
            });
        });



    };


    onDropTarget = (files) => {
        const onProgress = progressEvent => {
            this.setState({
                fileSizeTarget: Math.floor((progressEvent.total) / 1000),
                fileNameTarget: files[0].name,
                uploadTarget: {
                    progress: ((progressEvent.loaded * 100) / progressEvent.total),
                    start: true,
                    status: 'progress',
                }
            })
        };
        httpUpload(files[0], onProgress).then(response => {

            if (!response.errors) {
                httpConversion({
                    file_name: response.data[0].name,
                    source_lang: this.state.targetLang,
                    target_lang: this.state.sourceLang
                });
                this.setState({
                    fileNameTarget: response.data[0].name,
                    start: false,
                    uploadTarget: {
                        status: 'finish',
                        progress: 0
                    },

                });
            }
        }, (error) => {
            this.setState({
                uploadTarget: {
                    progress: 0,
                    status: 'error'
                }
            });
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

    renderHtmlUpload = (status, data) =>{
        switch (status) {
            case 'start':
                return <p><span>+ Add Target file</span> (or drop it here).</p>;

            case 'progress':
                return <div>
                    <div className="fileInfo">
                        <p>
                            <i id="error-icon" aria-hidden='true' className='file icon'/> {data.filename}
                        </p>
                    </div>
                    <p id="fileSize"> {data.filesize} kb </p>
                    <div className='ui progress' data-percent={data.progress}>
                        <div className='bar' style={{width: data.progress + '%'}}/>
                    </div>
                    <p id="actionInfo">Uploading</p>
                </div>;

            case 'finish':
                return <p>
                    <i id="file-icon" aria-hidden='true' className='file icon'/>
                    <span className="fileInfo">{data.filename}</span>
                    <span id="fileSize"> {data.filesize} kb </span>
                    <i id="delete-icon" aria-hidden='true' className='trash alternate outline icon'/>
                </p>;

            case 'error':
                return <p>
                    <i id="error-icon" aria-hidden='true'
                       className='window close outline icon'/>Error during file upload : <span> Server problem occurred. </span>
                    <i id="delete-icon" aria-hidden='true'
                       className='trash alternate outline icon'/>
                    <i id="triangle" aria-hidden='true' className='triangle right icon'/>
                </p>;
        }
    };

    render() {

        const uploadAreaStyle = {};
        let classes = {
            source: ['dropzone'],
            target: ['dropzone']
        };

        classes.source.push(this.state.uploadSource.status);
        classes.target.push(this.state.uploadTarget.status);

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
                        <div className="thirteen wide column">
                            <div className="ui input">
                                <input className="form-control" name="pname" type="text" value={this.state.pName}
                                       onChange={this.ProjectNameChange}/>
                            </div>
                        </div>

                        <div className="three wide column">
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
                            <div className={classes.source.join(' ')}>
                                <Dropzone style={uploadAreaStyle} onDrop={this.onDropSource}>
                                    {
                                        this.renderHtmlUpload(
                                            this.state.uploadSource.status,
                                            {
                                                filename: this.state.fileNameSource,
                                                progress: this.state.uploadSource.progress,
                                                filesize: this.state.fileSizeSource
                                            })
                                    }
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
                            <div className={classes.target.join(' ')}>
                                <Dropzone style={uploadAreaStyle} onDrop={this.onDropTarget}>
                                    {
                                        this.renderHtmlUpload(
                                            this.state.uploadTarget.status,
                                            {
                                                filename: this.state.fileNameTarget ,
                                                progress: this.state.uploadTarget.progress,
                                                filesize: this.state.fileSizeTarget,
                                            })
                                    }
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