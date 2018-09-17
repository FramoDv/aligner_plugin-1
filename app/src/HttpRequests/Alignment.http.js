import axios, {post,get} from 'axios';
import qs from 'qs';
import env from "../Constants/Env.constants";

/**
 *
 * @param data
 * @param {String} data.file_name
 * @param {String} data.source_lang
 * @param {String} data.target_lang
 * @return {Promise}
 */
export const httpConversion = (data) => {
    const url = '/plugins/aligner/xliff_conversion';
    return post(url, qs.stringify(data));
};
/**
 *
 * @param data
 * @param {String} data.project_name
 * @param {String} data.file_name_source
 * @param {String} data.file_name_target
 * @param {String} data.source_lang
 * @param {String} data.target_lang
 * @return {Promise}
 */
export const httpCreateProject = (data) => {
    const url = '/plugins/aligner/create_project';
    return post(url, qs.stringify(data));
};
/**
 *
 * @param {Number} jobID The job to align
 * @param {Number} algorithmVersion The version of algorithm to use, if isn't defined it use env.alignAlgorithmDefaultVersion
 * @return {Promise}
 */
export const httpAlignJob = (jobID, algorithmVersion=null) => {
    const algorithm = algorithmVersion ? algorithmVersion : env.alignAlgorithmDefaultVersion;
    const url = '/plugins/aligner/parse/v'+algorithm+'/'+jobID;
    return get(url);
};


export const httpGetSegments = (jobID) => {
    const url = '/plugins/aligner/segments/'+jobID;
    return get(url);
};

/**
 *
 * @param {Number} jobID
 * @param {String} jobPassword
 * @param {Object} data
 * @param {String} data.type
 * @param {Number} data.order
 * @param {Number} data.inverseOrder
 * @param {Array} data.positions
 * @return {*}
 */
export const httpSplitSegment = (jobID,jobPassword,data) => {
    const url = '/plugins/aligner/job/'+jobID+'/'+jobPassword+'/segment/split';
    const values = {
      type: data.type,
      order: data.order,
      inverse_order: data.inverseOrder,
      positions: data.positions
    };
    return post(url,qs.stringify(values));
};