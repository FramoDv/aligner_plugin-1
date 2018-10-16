import React, {Component} from 'react';
import PropTypes from "prop-types";
import ProjectActions from "../../../../../Actions/Project.actions";
import {Popup} from "semantic-ui-react";

class ToolbarActionsMergeComponent extends Component {

    static propTypes = {
        selection: PropTypes.object.isRequired,
        jobConf: PropTypes.shape({
            password: PropTypes.string,
            id: PropTypes.any
        })
    };

    constructor(props) {
        super(props);
        this.state = {
            type: 'merge'
        };
    }


    componentDidMount() {
    }

    componentWillUnmount() {
    }

    render() {
        //check status of merge action
        let disabled = false;
        let mergeClasses = ['icon', 'random'];
        if (
            !((this.props.selection.source.count === 0 && this.props.selection.target.count > 1)
                || (this.props.selection.target.count === 0 && this.props.selection.source.count > 1))
        ) {
            disabled = true;
        }
        return <span><button
            disabled={disabled}
            onMouseOut={this.onMouseLeave}
            onMouseOver={this.onHover}
            onClick={this.onMergeClick}>
            Merge
        </button></span>;
        /*
         return (
        <Popup trigger={comp} content='shortcut alt+M' on='hover' inverted/>
    );
    */

    }

    onMergeClick = () => {
        const type = this.props.selection.source.count > 0 ? 'source' : 'target';
        const orders = this.props.selection[type].list.sort();
        ProjectActions.mergeSegments(this.props.jobConf.id,this.props.jobConf.password,orders, type);
        ProjectActions.addSegmentToSelection(-1);
        ProjectActions.onActionHover(null);
    };

    onHover = () => {
        ProjectActions.onActionHover(this.state.type);
    };

    onMouseLeave = () => {
        ProjectActions.onActionHover(null);
    };

}

export default ToolbarActionsMergeComponent;