import React, {Component} from 'react';
import {ItemTypes} from '../../../Constants/Draggable.constants';
import {DropTarget} from 'react-dnd';
import ProjectActions from "../../../Actions/Project.actions";
import PropTypes from "prop-types";
import SegmentComponent from "./Segment/Segment.component";
import ProjectStore from "../../../Stores/Project.store";
import ProjectConstants from "../../../Constants/Project.constants";
import ReactDOM, {findDOMNode} from "react-dom";

const RowTarget = {
    canDrop(props, monitor) {
        const from = monitor.getItem();
        return from.segment.order !== props.row[from.type].order;
    },
    drop(props, monitor, component) {
        const from = monitor.getItem();
        const log = {
            type: from.type,
            from: from.segment.order,
            to: props.row[from.type].order
        };
        component.alignSegments(log)
    }
};

function collect(connect, monitor) {
    return {
        connectDropTarget: connect.dropTarget(),
        isOver: monitor.isOver(),
        isOverCurrent: monitor.isOver({shallow: true}),
        canDrop: monitor.canDrop(),
        dragEl: monitor.getItem()
    }
}

class RowComponent extends Component {

    static propTypes = {
        index: PropTypes.number.isRequired,
        animate: PropTypes.bool,
        setAnimatedRow: PropTypes.func,
        scrollY: PropTypes.any,
        enableDrag: PropTypes.bool,
        rec: PropTypes.any,
        row: PropTypes.object.isRequired,
        selection: PropTypes.object
    };

    constructor(props) {
        super(props);

        this.state = {
            animated: false
        };
    }

    static getDerivedStateFromProps(props, state) {
        if (props.animate && !state.animated) {
            state.animated = true;
        }
        return state;
    }

    shouldComponentUpdate(nextProps, nextState) {

        return true;
    }

    getSnapshotBeforeUpdate(prevProps, prevState) {

        return null;
    }

    componentDidUpdate(prevProps, prevState, snapshot) {

    }

    componentDidMount() {
        ProjectStore.addListener(ProjectConstants.ANIMATE_ROW_POSITION, this.animateRow);
        ProjectStore.addListener(ProjectConstants.REQUIRE_SEGMENT_CHANGE_POSITION, this.alignSegments);
        ProjectStore.addListener(ProjectConstants.SCROLL_TO_SEGMENT, this.scrollToThisSegment);

    }

    componentWillUnmount() {
        ProjectStore.removeListener(ProjectConstants.ANIMATE_ROW_POSITION, this.animateRow);
        ProjectStore.removeListener(ProjectConstants.REQUIRE_SEGMENT_CHANGE_POSITION, this.alignSegments);
        ProjectStore.removeListener(ProjectConstants.SCROLL_TO_SEGMENT, this.scrollToThisSegment);
    }

    render() {
        let rowClass = ['project-row'];
        const {connectDropTarget, isOver, isOverCurrent, canDrop, dragEl, selection, enableDrag} = this.props;

        const dragElType = dragEl ? dragEl.type : undefined;
        if (isOver && dragElType && canDrop) {
            rowClass.push("dropHover");
        }
        if (this.state.animated) {
            rowClass.push("droppedRow");
        }

        return connectDropTarget(
            <div className={rowClass.join(' ')} ref={re => {
                this.ref = re
            }}>
                <div>{this.props.index}</div>
                <SegmentComponent type="source"
                                  dropHover={isOver && canDrop && dragElType === 'source'}
                                  enableDrag={enableDrag}
                                  selected={selection && selection.source}
                                  segment={this.props.row.source}/>
                <SegmentComponent type="target"
                                  dropHover={isOver && canDrop && dragElType === 'target'}
                                  enableDrag={enableDrag}
                                  selected={selection && selection.target}
                                  segment={this.props.row.target}/>
            </div>
        );
    }

    scrollToThisSegment = (data) => {
        if (data.ref === this.ref) {
            const domNode = ReactDOM.findDOMNode(this.ref);
            if (data.y) {
                window.scrollTo(0, data.y);
            } else {
                domNode.scrollIntoView({
                    block: 'center',
                    behavior: 'smooth'
                })
            }

        }
    };

    animateRow = (data) => {
        const {type, order, position, rec} = data;
        if (this.props.row[type].order === order) {
            clearInterval(this.interval);
            this.scrollAfterDrop(position, rec);
            this.interval = setInterval(() => {
                if (this.state.animated) {
                    this.setState({
                        animated: false
                    })
                }
            }, 2000);
            this.setState({
                animated: true
            })
        } else if (this.state.animated) {
            this.setState({
                animated: false
            })
        }
    };

    /**
     *
     * @param {Object} log A log of move action from frontend
     * @param {String} log.type The type of segment: source or target
     * @param {Number} log.from The row's order of Drag action
     * @param {Number} log.to The row's order of Drop action
     */
    alignSegments = (log) => {
        if (log.to === this.props.row[log.type].order) {
            const inverse = {
                'source': 'target',
                'target': 'source'
            };
            const rec = findDOMNode(this.ref).getBoundingClientRect();
            const position = window.scrollY;
            const inverseOrder = this.props.row[inverse[log.type]].order


            setTimeout(() => {
                ProjectActions.changeSegmentPosition(log);
                //send type and order of inverse segment in drop position.
                setTimeout(() => {
                    ProjectActions.animateChangeRowPosition(inverse[log.type], inverseOrder, position, rec);
                }, 0)
            }, 0)
        }
    };

    scrollAfterDrop = (position, rec) => {
        const top = findDOMNode(this.ref).getBoundingClientRect().top;
        if (rec.top > top) {
            window.scrollTo(0, position - (rec.top - top));
        }
    };

    interval = null;

}

export default DropTarget(ItemTypes.ITEM, RowTarget, collect)(RowComponent);
