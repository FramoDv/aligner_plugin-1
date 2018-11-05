import React, {Component} from 'react';
import ProjectActions from "../../../Actions/Project.actions";
import PropTypes from "prop-types";
import SplitCharComponent from "./SplitCharComponent/SplitCharComponent.component";
import SplitDivisor from "./SplitDivisor/SplitDivisor.component";
import ModalHeader from "../../Shared/ModalHeader/ModalHeader.component";

class SplitComponent extends Component {
    static propTypes = {
        segment: PropTypes.object,
        inverseSegmentOrder: PropTypes.number,
        jobConf: PropTypes.shape({
            password: PropTypes.string,
            id: PropTypes.any
        })
    };

    constructor(props) {
        super(props);
        this.state = {
            splitModalStatus: false,
            segmentContent: this.props.segment.content_raw,
            chars: this.props.segment.content_raw.split(""),
            splits: {},
            charDictionary: this.fillDictionaries().charDictionary,
            wordDictionary: this.fillDictionaries().wordDictionary,
            tagPosition: this.getTagsPosition(),
            temporarySplitPosition: -1,
        };
    }

    getTagsPosition = () =>{
        const   less = /##LESSTHAN##/g;
        const   greater = /##GREATERTHAN##/g;
        let     match;
        let     lessArray = [];
        let     greaterArray = [];
        let tags = [];
        let result = [];
        while (match = less.exec(this.props.segment.content_raw)) {
            lessArray.push(match.index);
        }
        while (match = greater.exec(this.props.segment.content_raw)) {
            greaterArray.push(match.index);
        }

        lessArray.map((e,index)=>{
            tags.push({
                less: e,
                greater: greaterArray[index]+14
            })
        });
        tags.map((e)=>{
           for(let x = e.less; x <= e.greater; x++){
               result.push(x);
           }
        });
        console.log(result);

        return result

    };

    componentDidMount() {

    }

    componentWillUnmount() {

    }

    render = () => {
        return (
            <div>
                <div>
                    <div className="overlay" onClick={this.onCloseSplitModal}>
                    </div>
                    <div className="splitContainer">
                        <ModalHeader modalName={"split"}/>
                        <div className="content">
                            <p id="toSplit" onMouseLeave={() => this.onCharHover(-1)}>
                                {this.renderItems()}
                            </p>
                            <button className="ui button primary splitBtn" onClick={this.onSave}>Split</button>
                        </div>
                    </div>
                </div>
            </div>
        );
    };

    renderItems = () => {
        let items = [];
        let countSplittedItems = 0;
        let range = [-1, -1];
        const wordIndex = this.state.charDictionary[this.state.temporarySplitPosition];

        if (this.state.temporarySplitPosition > -1) {
            this.leftSigned(wordIndex, range);
            this.rightSigned(wordIndex, range);
        }

        this.state.chars.map((element, index) => {
            countSplittedItems++;
            {this.state.tagPosition.indexOf(index) < 0 && items.push(<SplitCharComponent
                word={element}
                key={countSplittedItems}
                signed={this.state.charDictionary[index] >= range[0] && this.state.charDictionary[index] <= range[1]}
                position={index}
                onClick={this.onCharClick}
                onHover={this.onCharHover}
            />);}
            if (this.state.splits[index]) {
                countSplittedItems++;
                items.push(<SplitDivisor
                    key={countSplittedItems}
                    position={index}
                    isIcon={true}
                    onClick={this.onCharClick}
                />)
            } else if (this.state.temporarySplitPosition === index) {
                countSplittedItems++;
                items.push(<SplitDivisor
                    key={countSplittedItems}
                    temporary={true}
                    position={index}
                    onClick={this.onCharClick}
                />)
            }
        });
        return items;
    };

    onCloseSplitModal = () => {
        ProjectActions.openSegmentToSplit(false);
    };

    /**
     * with this method we track the position of setted split cursor our in phrase
     * @param index
     */
    onCharClick = (index) => {
        if (index !== this.state.chars.length - 1) {
            let splits = this.state.splits;
            splits[index] ? splits[index] = false : splits[index] = true;
            this.setState({
                splits: splits
            })
        }
    };

    /**
     * with this method we track the real time split cursor position on hover
     * @param index
     */
    onCharHover = (index) => {
        if (this.state.splits[index] || index === this.state.chars.length - 1) {
            index = -1
        }
        this.setState({
            temporarySplitPosition: index,
        });
    };

    /**
     * this function will create and fill our dictionaries,
     * ready to use for ours calculations, to perform split operation.
     * @returns {{charDictionary: {}, wordDictionary: {}}}
     */
    fillDictionaries = () => {
        // words in our phrase splitted by space
        const words = this.props.segment.content_raw.split(" ");
        // dictionaries structure
        let dictionaries = {
            charDictionary: {},
            wordDictionary: {},
        };
        let charIndex = 0;
        let wordIndex = 0;
        // here we'll fill our dictionaries
        for (let x = 0; x < words.length; x++) {
            // for each word in words, we split our word in characters
            const word = words[x].split("");
            for (let y = 0; y < word.length; y++) {
                // we associate the character with the word
                dictionaries.charDictionary[charIndex] = wordIndex;
                charIndex++;
                /*
                    at the end of the chars count for this word,
                    we update wordDictionaries with word characters count
                */
                if (y === word.length - 1) {
                    dictionaries.wordDictionary[wordIndex] = y + 1;
                }
            }
            /*
                while we have word in words after char count,
                update wordindex, charDictionary, charindex...
             */
            if (x < words.length - 1) {
                wordIndex++;
                dictionaries.charDictionary[charIndex] = wordIndex;
                dictionaries.wordDictionary[wordIndex] = 1;
                charIndex++;
            }
            wordIndex++;
        }
        return dictionaries;
    };

    /**
     * we will calculate the characters that will be signed at the right
     * @param {Number} wordIndex
     * @param {Array} range
     */
    rightSigned = (wordIndex, range) => {
        for (let index = wordIndex; index < this.state.chars.length - 2; index++) {
            range[1] = index;
            if (this.state.wordDictionary[index + 1] > 1) {
                range[1] = index + 1;
                break
            }
        }
    };
    /**
     * we will calculate the characters that will be signed at the left
     * @param {Number} wordIndex
     * @param {Array} range
     */
    leftSigned = (wordIndex, range) => {
        for (let index = wordIndex; index > 0; index--) {
            range[0] = index;
            if (this.state.wordDictionary[index] > 1) {
                range[0] = index - 1;
                break
            }
        }
    };


    onSave = () => {
        let positions = [];
        Object.keys(this.state.splits).map(position => {
            if (this.state.splits[position]) {
                positions.push(position);
            }
        });
        const data = {
            type: this.props.segment.type,
            order: this.props.segment.order,
            inverseOrder: this.props.inverseSegmentOrder,
            positions: positions
        };
        ProjectActions.splitSegment(this.props.jobConf.id, this.props.jobConf.password,data);
        setTimeout(()=>{
            ProjectActions.openSegmentToSplit(false);
        },0)
    }
}

export default SplitComponent;
