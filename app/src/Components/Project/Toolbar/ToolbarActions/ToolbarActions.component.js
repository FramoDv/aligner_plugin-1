import React, {Component} from 'react';
import PropTypes from "prop-types";
import ToolbarActionsMergeComponent from "./ToolbarActionsMerge/ToolbarActionsMerge.component";
import ToolbarActionsReverseComponent from "./ToolbarActionsReverse/ToolbarActionsReverse.component";
import ToolbarActionsSplitComponent from "./ToolbarActionsSplit/ToolbarActionsSplit.component";
import ToolbarActionsAlignWrapperComponent from "./ToolbarActionsAlignWrapper/ToolbarActionsAlignWrapper.component";

class ToolbarActionsComponent extends Component {

    static propTypes = {
        selection: PropTypes.object.isRequired,
        jobConf: PropTypes.shape({
            password: PropTypes.string,
            id: PropTypes.any
        })
    };

    constructor(props) {
        super(props);
        this.state = {};
    }


    componentDidMount() {
    }

    componentWillUnmount() {
    }


    render() {
        return (
            <div className="segment-actions">
                <ul>
                    <li>
                        <ToolbarActionsAlignWrapperComponent selection={this.props.selection}/>
                    </li>
                    <li>
                        <ToolbarActionsMergeComponent selection={this.props.selection} jobConf={this.props.jobConf}/>

                    </li>
                    <li>
                        <ToolbarActionsSplitComponent selection={this.props.selection}/>
                    </li>
                    <li>
                        <ToolbarActionsReverseComponent selection={this.props.selection}/>
                    </li>
                    {/*<li>
                        <i
                            className={"icon pin"}
                        >
                        </i>
                    </li>
                    <li>
                        <i
                            className={"icon eye slash"}
                        >
                        </i>
                    </li>*/}
                </ul>
            </div>
        );
    }
}

export default ToolbarActionsComponent;
