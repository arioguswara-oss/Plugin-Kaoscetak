/* SANRO Studio editor geometry, no browser globals or server calls.
 * Geometry uses the admin-defined safe rectangle as origin and cm dimensions.
 * The backend remains authoritative for combined-span/category validation.
 */
(function(root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.SanroGeometry = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function() {
  'use strict';
  const categories = Object.freeze({
    A5: [14.8, 21], A4: [21, 29.7], A3: [29.7, 42], 'A3+': [32, 48]
  });
  function validArea(area) {
    return !!area && Number.isFinite(area.width) && Number.isFinite(area.height) &&
      area.width > 0 && area.height > 0 && area.width <= 100 && area.height <= 100;
  }
  function toCm(box, area) {
    if (!validArea(area) || !box || !['x','y','width','height'].every(k => Number.isFinite(box[k])))
      throw new TypeError('invalid_geometry');
    if (box.x < 0 || box.y < 0 || box.width <= 0 || box.height <= 0 ||
        box.x + box.width > 100 + 1e-8 || box.y + box.height > 100 + 1e-8)
      throw new RangeError('outside_safe_area');
    const round = n => Math.round(n * 10000) / 10000;
    return {
      x: round(box.x * area.width / 100),
      y: round(box.y * area.height / 100),
      width: round(box.width * area.width / 100),
      height: round(box.height * area.height / 100)
    };
  }
  function checkSide(objects, category, area) {
    if (!validArea(area)) return {valid:false,error:'invalid_safe_area'};
    if (!Object.prototype.hasOwnProperty.call(categories, category))
      return {valid:false,error:'invalid_category'};
    if (!Array.isArray(objects) || objects.length > 2) return {valid:false,error:'invalid_objects'};
    const seen = new Set(), cm = [];
    try {
      for (const object of objects) {
        if (!object || !['image','text'].includes(object.type) || seen.has(object.type))
          return {valid:false,error:'invalid_object_type'};
        seen.add(object.type);
        cm.push({type:object.type,...toCm(object,area)});
      }
    } catch(e) {return {valid:false,error:e.message}}
    if (!cm.length) return {valid:true,empty:true,objects:[],span_cm:[0,0]};
    const minX=Math.min(...cm.map(o=>o.x)),minY=Math.min(...cm.map(o=>o.y));
    const maxX=Math.max(...cm.map(o=>o.x+o.width)),maxY=Math.max(...cm.map(o=>o.y+o.height));
    const span=[maxX-minX,maxY-minY],limit=categories[category];
    if(span[0]>limit[0]+1e-6||span[1]>limit[1]+1e-6)
      return {valid:false,error:'category_exceeded',span_cm:span};
    return {valid:true,empty:false,objects:cm,span_cm:span};
  }
  return Object.freeze({categories,validArea,toCm,checkSide});
});
