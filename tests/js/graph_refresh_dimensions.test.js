/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../include/layout.js'), 'utf8');
const start = source.indexOf('function responsiveResizeGraphs(');
const resize = source.slice(start, source.indexOf('\nfunction resizeTreePanel(', start));

function fixture(type, width) {
	const attributes = {id:'graph_130', graph_id:'130', rra_id:'0', graph_type:type,
		image_width:800, image_height:360, canvas_top:30, canvas_left:60,
		canvas_width:700, canvas_height:200, width:800, height:360};
	const styles = {};
	const image = {
		attr(key, value) {if (value === undefined) return attributes[key]; attributes[key] = value; return this;},
		removeAttr(key) {delete attributes[key]; return this;},
		css(key, value) {styles[key] = value; return this;}
	};
	const empty = {length:0, val:()=>undefined, is:()=>false};
	const $ = selector => {
		if (selector === image) return image;
		if (selector === '.graphimage') return {each(callback) {callback.call(image);}};
		if (selector === '#wrapper_130') return {attr:key=>({graph_width:700,graph_height:200})[key]};
		return empty;
	};
	const context = vm.createContext({$, getMainWidth:()=>width+30, previousMainWidth:null, previousColumns:null});
	vm.runInContext(resize+';responsiveResizeGraphs(true);', context);
	return {attributes,styles};
}

test('an undecoded full-size PNG reserves its dimensions during a graph refresh', () => {
	const {attributes,styles} = fixture('png',1000);
	assert.equal(styles.width,'');
	assert.equal(styles.height,'');
	assert.equal(Number(attributes.width),800);
	assert.equal(Number(attributes.height),360);
});

test('smaller viewports still scale PNG graphs proportionally', () => {
	const {styles} = fixture('png',400);
	assert.equal(styles.width,400);
	assert.equal(styles.height,180);
});

test('SVG graphs retain explicit proportional CSS dimensions', () => {
	const {styles} = fixture('svg+xml',1000);
	assert.equal(styles.width,800);
	assert.equal(styles.height,360);
});
