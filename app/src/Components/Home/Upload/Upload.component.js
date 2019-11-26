import React, {Component} from 'react';
import PropTypes from "prop-types";
import {Dropdown} from 'semantic-ui-react'
import Dropzone from 'react-dropzone'
import {httpUpload} from '../../../HttpRequests/Upload.http';
import {httpConversion, httpCreateProject} from "../../../HttpRequests/Alignment.http";
import {Redirect} from "react-router"
import FileFormatsModal from "./FileFormatsModal/FileFormatsModal.component";

class UploadComponent extends Component {
    static propTypes = {};

    constructor() {
        super();
        let languages = [];
        this.progressInterval = null
        window.configs.languages.map(e => {
            languages.push({
                key: e.code,
                text: e.name,
                value: e.code
            })
        });
        this.state = {
            languages: languages,
            job: undefined,
            pName: '',
            uploadSource: {
                progress: 0,
                start: false,
                status: 'start',
                name: null,
                upload_name: null,
                size: 0,
                disabled: false
            },
            uploadTarget: {
                progress: 0,
                start: false,
                status: 'start',
                name: null,
                upload_name: null,
                size: 0,
                disabled: false
            },
            inAlign: false,
            sourceLang: 'en-US',
            targetLang: 'it-IT',
            formatsModalOpen: false,
            creationError: {
                error: false,
                message: null
            }
        }
    }


    onSourceLanguageChange = (e, value) => {
        if (this.state.uploadSource.name) {
            httpConversion({
                file_name: this.state.uploadSource.name,
                source_lang: value.value,
                target_lang: this.state.targetLang,
            }).catch(error => {
                this.setState({
                    uploadSource: {
                        progress: 0,
                        status: 'error'
                    }
                });
                __insp.push(['tagSession', {error: "file_conversion"}]);
            });
        }
        this.setState({
            sourceLang: value.value
        });
    };

    onTargetLanguageChange = (e, value) => {
        if (this.state.uploadTarget.name) {
            httpConversion({
                file_name: this.state.uploadTarget.name,
                source_lang: value.value,
                target_lang: this.state.sourceLang
            }).catch(error => {
                this.setState({
                    uploadSource: {
                        progress: 0,
                        status: 'error'
                    }
                });
                __insp.push(['tagSession', {error: "file_conversion"}]);
            });
        }
        this.setState({
            targetLang: value.value
        });
    };

    ProjectNameChange = (event) => {
        this.setState({pName: event.target.value});
    };

    onDropSource = async (files) => {
        if(!files.length){
            return this.setState({
                uploadSource: {
                    progress: 0,
                    status: 'multiple'
                }
            });
        }
        const onProgress = progressEvent => {
            this.setState({
                uploadSource: {
                    progress: ((progressEvent.loaded * 100) / progressEvent.total),
                    start: true,
                    status: 'progress',
                    size: Math.floor((progressEvent.total) / 1000),
                    name: files[0].name
                },
            });
        };
        try {
            const uploadResult = await httpUpload(files[0], onProgress);
            if(!uploadResult.errors || !uploadResult.data.errors){
                this.setState({
                    uploadSource: {
                        status: 'finish',
                        progress: 0,
                        name: uploadResult.data[0].name,
                        upload_name: uploadResult.data[0].storage_name,
                        size: Math.floor((files[0].size) / 1000),
                        disabled: true
                    },
                });

                const conversionResult = await httpConversion({
                    file_name: uploadResult.data[0].name,
                    source_lang: this.state.sourceLang,
                    target_lang: this.state.targetLang
                });
                if(conversionResult.data.code !== 1){
                    throw "conversion"
                }
            }
        }catch (e) {
            console.log(e)
            if(e === "conversion"){
                __insp.push(['tagSession', {error: "file_conversion"}]);
            }else{
                __insp.push(['tagSession', {error: "file_upload"}]);
            }
            this.setState({
                uploadSource: {
                    progress: 0,
                    status: 'error'
                }
            });

        }

    };

    // new function for increment
    simulateXliffProgress = (type,progress) => {
        console.log(progress)
        switch (type) {
            case "source":
                this.setState({
                    uploadSource: {
                        progress: ++progress,
                    },
                });
                break;
            case "target":
                this.setState({
                    uploadTarget: {
                        progress: ++progress,
                    },
                });
                break;
        }
    };


    onDropTarget = async (files) => {
        if(!files.length){
            return this.setState({
                uploadTarget: {
                    progress: 0,
                    status: 'multiple'
                }
            });
        }
        const onProgress = progressEvent => {
            const progressCalculated = ((progressEvent.loaded * 100) / (progressEvent.total));
            this.setState({
                uploadTarget: {
                    progress: progressCalculated >= 70 ? 70 : progressCalculated,
                    status: 'progress',
                    size: Math.floor((progressEvent.total) / 1000),
                    name: files[0].name
                },
            })
        };

        try {
            const uploadResult = await httpUpload(files[0], onProgress);
            if(!uploadResult.errors || !uploadResult.data.errors){
                // new code from here
                setTimeout( () => {
                    this.progressInterval = setInterval( () => {
                        if(this.state.uploadTarget.progress > 98){
                            clearInterval(this.progressInterval);
                        }else{
                            this.simulateXliffProgress("target",this.state.uploadTarget.progress);
                        }
                    }, 200);
                },8000);

                const conversionResult = await httpConversion({
                    file_name: uploadResult.data[0].name,
                    source_lang: this.state.targetLang,
                    target_lang: this.state.sourceLang
                });
                // moved at the bottom because now the component will be reset after xlif conversion
                this.setState({
                    uploadTarget: {
                        start: false,
                        status: 'finish',
                        progress: 0,
                        name: uploadResult.data[0].name,
                        upload_name: uploadResult.data[0].storage_name,
                        size: Math.floor((files[0].size) / 1000),
                        disabled: true
                    },
                });

                clearInterval(this.progressInterval);

                if(conversionResult.data.code !== 1){
                    throw "conversion"
                }
            }
        }catch (e) {
            console.log(e)
            if(e === "conversion"){
                __insp.push(['tagSession', {error: "file_conversion"}]);
            }else{
                __insp.push(['tagSession', {error: "file_upload"}]);
            }
            this.setState({
                uploadTarget: {
                    progress: 0,
                    status: 'error'
                }
            });
        }
    };

    startAlignment = () => {
        this.setState({
            inAlign: true
        });
        httpCreateProject({
            project_name: this.state.pName,
            file_name_source: this.state.uploadSource.upload_name,
            file_name_target: this.state.uploadTarget.upload_name,
            source_lang: this.state.sourceLang,
            target_lang: this.state.targetLang
        }).then(response => {
            this.setState({
                job: {
                    id: response.data.job.id,
                    password: response.data.job.password
                },
                creationError: {
                    error: false,
                    message: null
                },
                inAlign: false
            });
        }, (error) => {
            this.setState({
                creationError: {
                    error: true,
                    message: error.response.data.errors ? error.response.data.errors[0].message : 'An error occurred! retry or contact us.'
                },
                inAlign: false
            });
            __insp.push(['tagSession', {error: "create_project"}]);
        });
    };

    onFormatsModalClick = () => {
        this.setState({
            formatsModalOpen: !this.state.formatsModalOpen
        });
    };

    onDeleteFile = (type) => {
        switch (type) {
            case 'source':
                this.setState({
                    uploadSource: {
                        progress: 0,
                        start: false,
                        status: 'start',
                        name: null,
                        size: 0,
                        disabled: false,
                    },
                });
                break;
            case 'target':
                this.setState({
                    uploadTarget: {
                        progress: 0,
                        start: false,
                        status: 'start',
                        name: null,
                        size: 0,
                        disabled: false,
                    },
                });
                break;
            default:
                break;
        }
    };

    renderHtmlUpload = (type, status, data) => {
        switch (status) {
            case 'start':
                return <p><span>+ Add {type === 'target' ? "Target" : "Source"} file</span> (or drop it here).</p>;

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
                    <i id="delete-icon" aria-hidden='true' className='trash alternate outline icon' onClick={() => this.onDeleteFile(type)}/>
                </p>;

            case 'error':
                return <p>
                    <i id="error-icon" aria-hidden='true'
                       className='window close outline icon'/>Error during file upload
                    : <span> Server problem occurred. </span>
                    <i id="delete-icon" aria-hidden='true'
                       className='trash alternate outline icon'/>
                    <i id="triangle" aria-hidden='true' className='triangle right icon'/>
                </p>;

            case 'multiple':
                return <p>
                    <i id="error-icon" aria-hidden='true'
                       className='window close outline icon'/>Error during file upload
                    : <span> Multiple file not allowed. </span>
                    <i id="delete-icon" aria-hidden='true'
                       className='trash alternate outline icon'/>
                    <i id="triangle" aria-hidden='true' className='triangle right icon'/>
                </p>;
        }
    };

    render() {
        const {creationError} = this.state;
        const uploadAreaStyle = {};
        let classes = {
            source: ['dropzone'],
            target: ['dropzone']
        };
        let startButton = ['ui', 'primary', 'button'];
        if (this.state.inAlign) {
            startButton.push('loading');
        }

        classes.source.push(this.state.uploadSource.status === "multiple" ? "error" : this.state.uploadSource.status);
        classes.target.push(this.state.uploadTarget.status === "multiple" ? "error" : this.state.uploadTarget.status);

        if (this.state.job) {
            return <Redirect push to={'/job/' + this.state.job.id + '/' + this.state.job.password + '/pre-align'}/>;
        }

        return (
            <div className="uploadComponent">
                <div className="uploadCard ui grid">
                    <div className="row" id="projectNameHeader">
                        <h3 className="ui header">Alignment project name <span>(optional)</span></h3>
                    </div>

                    <div className="row" id="projectNameInput">
                        <div className="five wide column">
                            <div className="ui input">
                                <input id="project-name" className="form-control" name="pname" type="text"
                                       value={this.state.pName}
                                       onChange={this.ProjectNameChange}/>
                            </div>
                        </div>
                    </div>

                    <div className="row">
                        <div className="eight wide column">
                            <div className="">
                                <Dropdown fluid search selection
                                          options={this.state.languages}
                                          defaultValue={this.state.sourceLang}
                                          onChange={this.onSourceLanguageChange}
                                />
                            </div>
                            <div className="">
                                <div className={classes.source.join(' ')}>
                                    <Dropzone style={uploadAreaStyle} multiple={false} onDrop={this.onDropSource} disabled={this.state.uploadSource.disabled}>
                                        {
                                            this.renderHtmlUpload(
                                                "source",
                                                this.state.uploadSource.status,
                                                {
                                                    filename: this.state.uploadSource.name,
                                                    progress: this.state.uploadSource.progress,
                                                    filesize: this.state.uploadSource.size
                                                })
                                        }
                                    </Dropzone>
                                </div>
                            </div>
                        </div>

                        <div className="eight wide column">
                            <div className="">
                                <Dropdown fluid search selection
                                          options={this.state.languages}
                                          defaultValue={this.state.targetLang}
                                          onChange={this.onTargetLanguageChange}
                                />
                            </div>

                            <div className="">
                                <div className={classes.target.join(' ')}>
                                    <Dropzone style={uploadAreaStyle} multiple={false} onDrop={this.onDropTarget} disabled={this.state.uploadTarget.disabled}>
                                        {
                                            this.renderHtmlUpload(
                                                "target",
                                                this.state.uploadTarget.status,
                                                {
                                                    filename: this.state.uploadTarget.name,
                                                    progress: this.state.uploadTarget.progress,
                                                    filesize: this.state.uploadTarget.size,
                                                })
                                        }
                                    </Dropzone>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="row" id="buttonRow">

                        <div className="twelve wide column">
                            <h4>MateCat supports <span onClick={this.onFormatsModalClick}> 69 file formats </span></h4>
                            {this.state.formatsModalOpen &&
                            <FileFormatsModal formatModalState={this.onFormatsModalClick}/>}
                        </div>


                        <div className="four wide column">
                            <button className={startButton.join(" ")} onClick={this.startAlignment}
                                    disabled={!this.state.uploadSource.name || !this.state.uploadTarget.name || this.state.inAlign}
                            >START ALIGNING
                            </button>
                            {creationError.error &&
                            <div className={"creation-error"}>
                                <span> {creationError.message} </span>
                            </div>
                            }
                        </div>
                    </div>
                </div>
            </div>
        );
    }
}

export default UploadComponent;
