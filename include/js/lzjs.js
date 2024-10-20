/*!
 * lzjs v1.3.1 - compressing strings using an original algorithm based on the LZ algorithm
 * Copyright (c) 2014 polygonplanet <polygon.planet.aqua@gmail.com>
 * https://github.com/polygonplanet/lzjs
 * @license MIT
 */
(function(name, context, factory) {
    // Supports UMD. AMD, CommonJS/Node.js and browser context
    if (typeof exports !== 'undefined') {
        if (typeof module !== 'undefined' && module.exports) {
            module.exports = factory();
        } else {
            exports[name] = factory();
        }
    } else if (typeof define === 'function' && define.amd) {
        define(factory);
    } else {
        context[name] = factory();
    }
}('lzjs', this, function() {
    var fromCharCode = String.fromCharCode;
    var hasOwnProperty = Object.prototype.hasOwnProperty;
    var HAS_TYPED = typeof Uint8Array !== 'undefined' && typeof Uint16Array !== 'undefined';

    // Test for String.fromCharCode.apply
    var CAN_CHARCODE_APPLY = false;
    var CAN_CHARCODE_APPLY_TYPED = false;

    try {
        if (fromCharCode.apply(null, [0x61]) === 'a') {
            CAN_CHARCODE_APPLY = true;
        }
    } catch (e) {}

    if (HAS_TYPED) {
        try {
            if (fromCharCode.apply(null, new Uint8Array([0x61])) === 'a') {
                CAN_CHARCODE_APPLY_TYPED = true;
            }
        } catch (e) {}
    }

    // Stack maximum range of Function.prototype.apply
    var APPLY_BUFFER_SIZE = 65533;
    var APPLY_BUFFER_SIZE_OK = null;

    // IE has a bug in String.prototype.lastIndexOf when the second argument is specified
    var STRING_LASTINDEXOF_BUG = false;
    if ('abc\u307b\u3052'.lastIndexOf('\u307b\u3052', 1) !== -1) {
        STRING_LASTINDEXOF_BUG = true;
    }

    var TABLE = (function() {
        var table = '';
        var esc = {
            0x8: 1,
            0xa: 1,
            0xb: 1,
            0xc: 1,
            0xd: 1,
            0x5c: 1
        };

        for (var i = 0; i < 0x7f; i++) {
            if (!hasOwnProperty.call(esc, i)) {
                table += fromCharCode(i);
            }
        }

        return table;
    }());

    // Buffers
    var TABLE_LENGTH = TABLE.length;
    var TABLE_DIFF = Math.max(TABLE_LENGTH, 62) - Math.min(TABLE_LENGTH, 62);
    var BUFFER_MAX = TABLE_LENGTH - 1;
    // var TABLE_BUFFER_MAX = BUFFER_MAX * (BUFFER_MAX + 1);

    // Sliding Window
    var WINDOW_MAX = 1024;
    var WINDOW_BUFFER_MAX = 304; // maximum 304

    // Chunk buffer length
    var COMPRESS_CHUNK_SIZE = APPLY_BUFFER_SIZE;
    var COMPRESS_CHUNK_MAX = COMPRESS_CHUNK_SIZE - TABLE_LENGTH;
    var DECOMPRESS_CHUNK_SIZE = APPLY_BUFFER_SIZE;
    var DECOMPRESS_CHUNK_MAX = DECOMPRESS_CHUNK_SIZE + WINDOW_MAX * 2;

    // Unicode table : U+0000 - U+0084
    var LATIN_CHAR_MAX = 11;
    var LATIN_BUFFER_MAX = LATIN_CHAR_MAX * (LATIN_CHAR_MAX + 1);

    // Unicode table : U+0000 - U+FFFF
    var UNICODE_CHAR_MAX = 40;
    var UNICODE_BUFFER_MAX = UNICODE_CHAR_MAX * (UNICODE_CHAR_MAX + 1);

    // Index positions
    var LATIN_INDEX = TABLE_LENGTH + 1;
    var LATIN_INDEX_START = TABLE_DIFF + 20;
    var UNICODE_INDEX = TABLE_LENGTH + 5;

    // Decode/Start positions
    var DECODE_MAX = TABLE_LENGTH - TABLE_DIFF - 19;
    var LATIN_DECODE_MAX = UNICODE_CHAR_MAX + 7;
    var CHAR_START = LATIN_DECODE_MAX + 1;
    var COMPRESS_START = CHAR_START + 1;
    var COMPRESS_FIXED_START = COMPRESS_START + 5;
    var COMPRESS_INDEX = COMPRESS_FIXED_START + 5; // 59

    // LZSS Compressor is based on the lzbase62 v1.4.6 (MIT license) compression algorithm
    // https://github.com/polygonplanet/lzbase62
    function LZSSCompressor(options) {
        this._init(options);
    }

    LZSSCompressor.prototype = {
        _init: function(options) {
            options = options || {};

            this._data = null;
            this._table = null;
            this._result = null;
            this._onDataCallback = options.onData;
            this._onEndCallback = options.onEnd;
            this._maxBytes = options.maxBytes;
        },
        _createTable: function() {
            var table = createBuffer(8, TABLE_LENGTH);
            for (var i = 0; i < TABLE_LENGTH; i++) {
                table[i] = TABLE.charCodeAt(i);
            }
            return table;
        },
        _onData: function(buffer, length) {
            var data = bufferToString_fast(buffer, length);

            if (this._onDataCallback) {
                this._onDataCallback(data);
            } else {
                this._result += data;
            }
        },
        _onEnd: function() {
            if (this._onEndCallback) {
                this._onEndCallback();
            }
            this._data = this._table = null;
        },
        // Searches for a longest match
        _search: function() {
            var i = 2;
            var data = this._data;
            var offset = this._offset;
            var len = BUFFER_MAX;
            if (this._dataLen - offset < len) {
                len = this._dataLen - offset;
            }
            if (i > len) {
                return false;
            }

            var pos = offset - WINDOW_BUFFER_MAX;
            var win = data.substring(pos, offset + len);
            var limit = offset + i - 3 - pos;
            var j, s, index, lastIndex, bestIndex, winPart;

            do {
                if (i === 2) {
                    s = data.charAt(offset) + data.charAt(offset + 1);

                    // Faster check by pre-matching slow lastIndexOf
                    index = win.indexOf(s);
                    if (index === -1 || index > limit)  {
                        break;
                    }
                } else if (i === 3) {
                    s = s + data.charAt(offset + 2);
                } else {
                    s = data.substr(offset, i);
                }

                if (STRING_LASTINDEXOF_BUG) {
                    winPart = data.substring(pos, offset + i - 1);
                    lastIndex = winPart.lastIndexOf(s);
                } else {
                    lastIndex = win.lastIndexOf(s, limit);
                }

                if (lastIndex === -1) {
                    break;
                }

                bestIndex = lastIndex;
                j = pos + lastIndex;
                do {
                    if (data.charCodeAt(offset + i) !== data.charCodeAt(j + i)) {
                        break;
                    }
                } while (++i < len);

                if (index === lastIndex) {
                    i++;
                    break;
                }

            } while (++i < len);

            if (i === 2) {
                return false;
            }

            this._index = WINDOW_BUFFER_MAX - bestIndex;
            this._length = i - 1;
            return true;
        },
        compress: function(data) {
            if (data == null || data.length === 0) {
                return '';
            }

            var result = '';
            var table = this._createTable();
            var win = createWindow();
            var buffer = createBuffer(8, COMPRESS_CHUNK_SIZE);
            var i = 0;
            var bytes = 0;

            this._result = '';
            this._offset = win.length;
            this._data = win + data;
            this._dataLen = this._data.length;
            win = data = null;

            var index = -1;
            var lastIndex = -1;
            var c, c1, c2, c3, c4;

            while (this._offset < this._dataLen) {
                if (!this._search()) {
                    c = this._data.charCodeAt(this._offset++);
                    if (c < LATIN_BUFFER_MAX) {
                        if (c < UNICODE_CHAR_MAX) {
                            c1 = c;
                            c2 = 0;
                            index = LATIN_INDEX;
                        } else {
                            c1 = c % UNICODE_CHAR_MAX;
                            c2 = (c - c1) / UNICODE_CHAR_MAX;
                            index = c2 + LATIN_INDEX;
                        }

                        // Latin index
                        if (lastIndex === index) {
                            buffer[i++] = table[c1];
                            bytes++;
                        } else {
                            buffer[i++] = table[index - LATIN_INDEX_START];
                            buffer[i++] = table[c1];
                            bytes += 2;
                            lastIndex = index;
                        }
                    } else {
                        if (c < UNICODE_BUFFER_MAX) {
                            c1 = c;
                            c2 = 0;
                            index = UNICODE_INDEX;
                        } else {
                            c1 = c % UNICODE_BUFFER_MAX;
                            c2 = (c - c1) / UNICODE_BUFFER_MAX;
                            index = c2 + UNICODE_INDEX;
                        }

                        if (c1 < UNICODE_CHAR_MAX) {
                            c3 = c1;
                            c4 = 0;
                        } else {
                            c3 = c1 % UNICODE_CHAR_MAX;
                            c4 = (c1 - c3) / UNICODE_CHAR_MAX;
                        }

                        // Unicode index
                        if (lastIndex === index) {
                            buffer[i++] = table[c3];
                            buffer[i++] = table[c4];
                            bytes += 2;
                        } else {
                            buffer[i++] = table[CHAR_START];
                            buffer[i++] = table[index - TABLE_LENGTH];
                            buffer[i++] = table[c3];
                            buffer[i++] = table[c4];
                            bytes += 4;

                            lastIndex = index;
                        }
                    }
                } else {
                    if (this._index < BUFFER_MAX) {
                        c1 = this._index;
                        c2 = 0;
                    } else {
                        c1 = this._index % BUFFER_MAX;
                        c2 = (this._index - c1) / BUFFER_MAX;
                    }

                    if (this._length === 2) {
                        buffer[i++] = table[c2 + COMPRESS_FIXED_START];
                        buffer[i++] = table[c1];
                        bytes += 2;
                    } else {
                        buffer[i++] = table[c2 + COMPRESS_START];
                        buffer[i++] = table[c1];
                        buffer[i++] = table[this._length];
                        bytes += 3;
                    }

                    this._offset += this._length;
                    if (~lastIndex) {
                        lastIndex = -1;
                    }
                }

                if (bytes > this._maxBytes) {
                    return false;
                }

                if (i >= COMPRESS_CHUNK_MAX) {
                    this._onData(buffer, i);
                    i = 0;
                }
            }

            if (i > 0) {
                this._onData(buffer, i);
            }

            this._onEnd();
            result = this._result;
            this._result = null;
            return result === null ? '' : result;
        }
    };

    // LZSS Decompressor
    function LZSSDecompressor(options) {
        this._init(options);
    }

    LZSSDecompressor.prototype = {
        _init: function(options) {
            options = options || {};

            this._result = null;
            this._onDataCallback = options.onData;
            this._onEndCallback = options.onEnd;
        },
        _createTable: function() {
            var table = {};
            for (var i = 0; i < TABLE_LENGTH; i++) {
                table[TABLE.charAt(i)] = i;
            }
            return table;
        },
        _onData: function(ended) {
            var data;

            if (this._onDataCallback) {
                if (ended) {
                    data = this._result;
                    this._result = [];
                } else {
                    var len = DECOMPRESS_CHUNK_SIZE - WINDOW_MAX;
                    data = this._result.slice(WINDOW_MAX, WINDOW_MAX + len);

                    this._result = this._result.slice(0, WINDOW_MAX).concat(
                        this._result.slice(WINDOW_MAX + len));
                }

                if (data.length > 0) {
                    this._onDataCallback(bufferToString_fast(data));
                }
            }
        },
        _onEnd: function() {
            if (this._onEndCallback) {
                this._onEndCallback();
            }
        },
        decompress: function(data) {
            if (data == null || data.length === 0) {
                return '';
            }

            this._result = stringToArray(createWindow());
            var result = '';
            var table = this._createTable();

            var out = false;
            var index = null;
            var len = data.length;
            var offset = 0;

            var i, c, c2, c3;
            var code, pos, length, sub, subLen, expandLen;

            for (; offset < len; offset++) {
                c = table[data.charAt(offset)];
                if (c === void 0) {
                    continue;
                }

                if (c < DECODE_MAX) {
                    if (!out) {
                        // Latin index
                        code = index * UNICODE_CHAR_MAX + c;
                    } else {
                        // Unicode index
                        c3 = table[data.charAt(++offset)];
                        code = c3 * UNICODE_CHAR_MAX + c + UNICODE_BUFFER_MAX * index;
                    }
                    this._result[this._result.length] = code;
                } else if (c < LATIN_DECODE_MAX) {
                    // Latin starting point
                    index = c - DECODE_MAX;
                    out = false;
                } else if (c === CHAR_START) {
                    // Unicode starting point
                    c2 = table[data.charAt(++offset)];
                    index = c2 - 5;
                    out = true;
                } else if (c < COMPRESS_INDEX) {
                    c2 = table[data.charAt(++offset)];

                    if (c < COMPRESS_FIXED_START) {
                        pos = (c - COMPRESS_START) * BUFFER_MAX + c2;
                        length = table[data.charAt(++offset)];
                    } else {
                        pos = (c - COMPRESS_FIXED_START) * BUFFER_MAX + c2;
                        length = 2;
                    }

                    sub = this._result.slice(-pos);
                    if (sub.length > length) {
                        sub.length = length;
                    }
                    subLen = sub.length;

                    if (sub.length > 0) {
                        expandLen = 0;
                        while (expandLen < length) {
                            for (i = 0; i < subLen; i++) {
                                this._result[this._result.length] = sub[i];
                                if (++expandLen >= length) {
                                    break;
                                }
                            }
                        }
                    }
                    index = null;
                }

                if (this._result.length >= DECOMPRESS_CHUNK_MAX) {
                    this._onData();
                }
            }

            this._result = this._result.slice(WINDOW_MAX);
            this._onData(true);
            this._onEnd();

            result = bufferToString_fast(this._result);
            this._result = null;
            return result;
        }
    };

    // LZW Compression
    function LZW(options) {
        this._init(options);
    }

    LZW.prototype = {
        _init: function(options) {
            options = options || {};

            this._codeStart = options.codeStart || 0xff;
            this._codeMax = options.codeMax || 0xffff;
            this._maxBytes = options.maxBytes;
        },
        compress: function(data) {
            if (data == null || data.length === 0) {
                return '';
            }

            var result = '';
            var resultBytes = 0;
            var i = 0;
            var len = data.length;
            var buffer = '';
            var code = this._codeStart + 1;
            var codeMax = this._codeMax;
            var codeBytes = 2;

            var dict = [];
            var dictLen = 0;

            var c, key, bitLen, length;

            if (len > 0) {
                buffer = c = data.charAt(i++);
            }

            while (i < len) {
                c = data.charAt(i++);

                key = buffer + c;
                length = key.length;
                bitLen = 1 << length;

                if (((length < 32 && (dictLen & bitLen)) ||
                        (length >= 32 && dict[length] !== void 0)) &&
                    hasOwnProperty.call(dict[length], key)) {
                    buffer += c;
                } else {
                    if (buffer.length === 1) {
                        result += buffer;
                        resultBytes++;
                    } else {
                        result += dict[buffer.length][buffer];
                        resultBytes += codeBytes;
                    }

                    if (resultBytes > this._maxBytes) {
                        return false;
                    }

                    if (code <= codeMax) {
                        key = buffer + c;
                        length = key.length;
                        bitLen = 1 << length;

                        if ((length < 32 && !(dictLen & bitLen)) ||
                            (length >= 32 && dict[length] === void 0)) {
                            dict[length] = {};
                            dictLen |= bitLen;
                        }

                        dict[length][key] = fromCharCode(code++);
                        if (code === 0x800) {
                            codeBytes = 3;
                        }
                    }

                    buffer = c;
                }
            }

            if (buffer.length === 1) {
                result += buffer;
                resultBytes++;
            } else {
                result += dict[buffer.length][buffer];
                resultBytes += codeBytes;
            }

            if (resultBytes > this._maxBytes) {
                return false;
            }

            return result;
        },
        decompress: function(data) {
            if (data == null || data.length === 0) {
                return '';
            }

            var result = '';

            var dict = {};
            var code = this._codeStart + 1;
            var codeMax = this._codeStart;

            var i = 0;
            var len = data.length;
            var c, ch, prev, buffer;

            if (len > 0) {
                c = data.charCodeAt(i++);
                ch = fromCharCode(c);
                result += ch;
                prev = ch;
            }

            while (i < len) {
                c = data.charCodeAt(i++);

                if (c <= codeMax) {
                    buffer = fromCharCode(c);
                } else {
                    if (hasOwnProperty.call(dict, c)) {
                        buffer = dict[c];
                    } else {
                        buffer = prev + ch;
                    }
                }

                result += buffer;

                ch = buffer.charAt(0);
                dict[code++] = prev + ch;
                prev = buffer;
            }

            return result;
        }
    };

    // LZJS Compression
    function LZJS(options) {
        this._init(options);
    }

    LZJS.prototype = {
        _init: function(options) {
            options = options || {};

            //TODO: Validate utf-8 encoding for command line.
            this._encoding = options.encoding || 'utf-8';
        },
        compress: function(data) {
            if (data == null || data.length === 0) {
                return '';
            }

            data = '' + data;

            var result = '';
            var dataBytes = byteLength(data);
            var asciiLimitBytes = dataBytes * 0.9 | 0;
            var len = data.length;
            var options = {
                maxBytes: dataBytes
            };
            var type;

            if (dataBytes === len) {
                // Ascii string [U+0000 - U+007F]
                type = 'W';
                options.codeStart = 0x7f;
                options.codeMax = 0x7ff;
                result = new LZW(options).compress(data);
                if (result === false) {
                    type = 'S';
                    result = new LZSSCompressor(options).compress(data);
                    if (result === false) {
                        type = 'N';
                        result = data;
                    }
                }
            } else if (dataBytes > len && asciiLimitBytes < len) {
                // String consisting mostly of ASCII
                type = 'U';
                result = new LZW(options).compress(toUTF8(data));
                if (result === false) {
                    type = 'S';
                    result = new LZSSCompressor(options).compress(data);
                    if (result === false) {
                        type = 'N';
                        result = data;
                    }
                }
            } else {
                // String consisting mostly of non-ASCII
                type = 'S';
                result = new LZSSCompressor(options).compress(data);
                if (result === false) {
                    type = 'U';
                    result = new LZW(options).compress(toUTF8(data));
                    if (result === false || byteLength(result) > dataBytes) {
                        type = 'N';
                        result = data;
                    }
                }
            }

            return type + result;
        },
        decompress: function(data) {
            if (data == null || data.length === 0) {
                return '';
            }

            data = '' + data;
            var type = data.charAt(0);

            switch (type) {
                case 'S': return this._decompressByS(data.substring(1));
                case 'W': return this._decompressByW(data.substring(1));
                case 'U': return this._decompressByU(data.substring(1));
                case 'N': return this._decompressByN(data.substring(1));
                default: return data;
            }
        },
        _decompressByS: function(data) {
            return new LZSSDecompressor().decompress(data);
        },
        _decompressByW: function(data) {
            var options = {
                codeStart: 0x7f,
                codeMax: 0x7ff
            };
            return new LZW(options).decompress(data);
        },
        _decompressByU: function(data) {
            return toUTF16(new LZW().decompress(data));
        },
        _decompressByN: function(data) {
            return data;
        }
    };

    // Create Sliding window
    function createWindow() {
        var alpha = 'abcdefghijklmnopqrstuvwxyz';
        var win = '';
        var len = alpha.length;
        var i, j, c, c2;

        for (i = 0; i < len; i++) {
            c = alpha.charAt(i);
            for (j = len - 1; j > 15 && win.length < WINDOW_MAX; j--) {
                c2 = alpha.charAt(j);
                win += ' ' + c + ' ' + c2;
            }
        }

        while (win.length < WINDOW_MAX) {
            win = ' ' + win;
        }
        win = win.slice(0, WINDOW_MAX);

        return win;
    }

    function truncateBuffer(buffer, length) {
        if (buffer.length === length) {
            return buffer;
        }

        if (buffer.subarray) {
            return buffer.subarray(0, length);
        }

        buffer.length = length;
        return buffer;
    }

    function bufferToString_fast(buffer, length) {
        if (length == null) {
            length = buffer.length;
        } else {
            buffer = truncateBuffer(buffer, length);
        }

        if (CAN_CHARCODE_APPLY && CAN_CHARCODE_APPLY_TYPED) {
            if (length < APPLY_BUFFER_SIZE) {
                if (APPLY_BUFFER_SIZE_OK) {
                    return fromCharCode.apply(null, buffer);
                }

                if (APPLY_BUFFER_SIZE_OK === null) {
                    try {
                        var s = fromCharCode.apply(null, buffer);
                        if (length > APPLY_BUFFER_SIZE) {
                            APPLY_BUFFER_SIZE_OK = true;
                        }
                        return s;
                    } catch (e) {
                        // Ignore RangeError: arguments too large
                        APPLY_BUFFER_SIZE_OK = false;
                    }
                }
            }
        }

        return bufferToString_chunked(buffer);
    }

    function bufferToString_chunked(buffer) {
        var string = '';
        var length = buffer.length;
        var i = 0;
        var sub;

        while (i < length) {
            if (buffer.subarray) {
                sub = buffer.subarray(i, i + APPLY_BUFFER_SIZE);
            } else {
                sub = buffer.slice(i, i + APPLY_BUFFER_SIZE);
            }
            i += APPLY_BUFFER_SIZE;

            if (APPLY_BUFFER_SIZE_OK) {
                string += fromCharCode.apply(null, sub);
                continue;
            }

            if (APPLY_BUFFER_SIZE_OK === null) {
                try {
                    string += fromCharCode.apply(null, sub);
                    if (sub.length > APPLY_BUFFER_SIZE) {
                        APPLY_BUFFER_SIZE_OK = true;
                    }
                    continue;
                } catch (e) {
                    APPLY_BUFFER_SIZE_OK = false;
                }
            }

            return bufferToString_slow(buffer);
        }

        return string;
    }

    function bufferToString_slow(buffer) {
        var string = '';
        var length = buffer.length;

        for (var i = 0; i < length; i++) {
            string += fromCharCode(buffer[i]);
        }

        return string;
    }

    function createBuffer(bits, size) {
        if (!HAS_TYPED) {
            return new Array(size);
        }

        switch (bits) {
            case 8: return new Uint8Array(size);
            case 16: return new Uint16Array(size);
        }
    }

    function stringToArray(string) {
        var array = [];
        var len = string && string.length;

        for (var i = 0; i < len; i++) {
            array[i] = string.charCodeAt(i);
        }

        return array;
    }

    // UTF-16 to UTF-8
    function toUTF8(data) {
        var results = [];
        var i = 0;
        var len = data.length;
        var c, second;

        for (; i < len; i++) {
            c = data.charCodeAt(i);

            // high surrogate
            if (c >= 0xd800 && c <= 0xdbff && i + 1 < len) {
                second = data.charCodeAt(i + 1);
                // low surrogate
                if (second >= 0xdc00 && second <= 0xdfff) {
                    c = (c - 0xd800) * 0x400 + second - 0xdc00 + 0x10000;
                    i++;
                }
            }

            if (c < 0x80) {
                results[results.length] = c;
            } else if (c < 0x800) {
                results[results.length] = 0xc0 | ((c >> 6) & 0x1f);
                results[results.length] = 0x80 | (c & 0x3f);
            } else if (c < 0x10000) {
                results[results.length] = 0xe0 | ((c >> 12) & 0xf);
                results[results.length] = 0x80 | ((c >> 6) & 0x3f);
                results[results.length] = 0x80 | (c & 0x3f);
            } else if (c < 0x200000) {
                results[results.length] = 0xf0 | ((c >> 18) & 0xf);
                results[results.length] = 0x80 | ((c >> 12) & 0x3f);
                results[results.length] = 0x80 | ((c >> 6) & 0x3f);
                results[results.length] = 0x80 | (c & 0x3f);
            }
        }

        return bufferToString_fast(results);
    }

    // UTF-8 to UTF-16
    function toUTF16(data) {
        var results = [];
        var i = 0;
        var len = data.length;
        var n, c, c2, c3, c4, code;

        while (i < len) {
            c = data.charCodeAt(i++);
            n = c >> 4;
            if (n >= 0 && n <= 7) {
                code = c;
            } else if (n === 12 || n === 13) {
                c2 = data.charCodeAt(i++);
                code = ((c & 0x1f) << 6) | (c2 & 0x3f);
            } else if (n === 14) {
                c2 = data.charCodeAt(i++);
                c3 = data.charCodeAt(i++);
                code = ((c & 0x0f) << 12) |
                    ((c2 & 0x3f) << 6) |
                    (c3 & 0x3f);
            } else if (n === 15) {
                c2 = data.charCodeAt(i++);
                c3 = data.charCodeAt(i++);
                c4 = data.charCodeAt(i++);
                code = ((c & 0x7) << 18)   |
                    ((c2 & 0x3f) << 12) |
                    ((c3 & 0x3f) << 6)  |
                    (c4 & 0x3f);
            }

            if (code <= 0xffff) {
                results[results.length] = code;
            } else {
                // Split in surrogate halves
                code -= 0x10000;
                results[results.length] = (code >> 10) + 0xd800; // High surrogate
                results[results.length] = (code % 0x400) + 0xdc00; // Low surrogate
            }
        }

        return bufferToString_fast(results);
    }

    // UTF-8 byte length
    function byteLength(data, encoding) {
        var length = 0;
        var c, c2;

        for (var i = 0, len = data.length; i < len; i++) {
            c = data.charCodeAt(i);

            if ((c & 0xfc00) === 0xd800 && (i + 1 < len)) {
                c2 = data.charCodeAt(i + 1);
                if ((c2 & 0xfc00) === 0xdc00) {
                    c = 0x10000 + ((c - 0xd800) << 10) + (c2 - 0xdc00);
                    i++;
                }
            }

            if (c < 0x80) {
                length++;
            } else if (c < 0x800) {
                length += 2;
            } else if (c < 0x10000) {
                length += 3;
            } else {
                length += 4;
            }
        }

        return length;
    }

    // Base64 from http://www.onicos.com/staff/iz/amuse/javascript/expert/base64.txt
    /*
     * Copyright (C) 1999 Masanao Izumo <iz@onicos.co.jp>
     * Version: 1.0
     * LastModified: Dec 25 1999
     * This library is free.  You can redistribute it and/or modify it.
     */
    var base64EncodeChars =
        'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

    var base64DecodeChars = [
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, 62, -1, -1, -1, 63,
        52, 53, 54, 55, 56, 57, 58, 59, 60, 61, -1, -1, -1, -1, -1, -1,
        -1,  0,  1,  2,  3,  4,  5,  6,  7,  8,  9, 10, 11, 12, 13, 14,
        15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, -1, -1, -1, -1, -1,
        -1, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40,
        41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, -1, -1, -1, -1, -1
    ];

    function base64encode(str) {
        var out, i, len;
        var c1, c2, c3;

        len = str.length;
        i = 0;
        out = '';
        while (i < len) {
            c1 = str.charCodeAt(i++) & 0xff;
            if (i === len) {
                out += base64EncodeChars.charAt(c1 >> 2) +
                    base64EncodeChars.charAt((c1 & 0x3) << 4) +
                    '==';
                break;
            }

            c2 = str.charCodeAt(i++);
            if (i === len) {
                out += base64EncodeChars.charAt(c1 >> 2) +
                    base64EncodeChars.charAt(((c1 & 0x3) << 4) | ((c2 & 0xf0) >> 4)) +
                    base64EncodeChars.charAt((c2 & 0xf) << 2) +
                    '=';
                break;
            }

            c3 = str.charCodeAt(i++);
            out += base64EncodeChars.charAt(c1 >> 2) +
                base64EncodeChars.charAt(((c1 & 0x3) << 4) | ((c2 & 0xf0) >> 4)) +
                base64EncodeChars.charAt(((c2 & 0xf) << 2) | ((c3 & 0xc0) >> 6)) +
                base64EncodeChars.charAt(c3 & 0x3f);
        }

        return out;
    }

    function base64decode(str) {
        var c1, c2, c3, c4;
        var i, len, out;

        len = str.length;
        i = 0;
        out = '';

        while (i < len) {
            do {
                c1 = base64DecodeChars[str.charCodeAt(i++) & 0xff];
            } while (i < len && c1 === -1);

            if (c1 === -1) {
                break;
            }

            do {
                c2 = base64DecodeChars[str.charCodeAt(i++) & 0xff];
            } while (i < len && c2 === -1);

            if (c2 === -1) {
                break;
            }

            out += fromCharCode((c1 << 2) | ((c2 & 0x30) >> 4));

            do {
                c3 = str.charCodeAt(i++) & 0xff;
                if (c3 === 61) {
                    return out;
                }
                c3 = base64DecodeChars[c3];
            } while (i < len && c3 === -1);

            if (c3 === -1) {
                break;
            }

            out += fromCharCode(((c2 & 0xf) << 4) | ((c3 & 0x3c) >> 2));

            do {
                c4 = str.charCodeAt(i++) & 0xff;
                if (c4 === 61) {
                    return out;
                }
                c4 = base64DecodeChars[c4];
            } while (i < len && c4 === -1);

            if (c4 === -1) {
                break;
            }

            out += fromCharCode(((c3 & 0x03) << 6) | c4);
        }

        return out;
    }

    /**
     * @name lzjs
     * @type {Object}
     * @public
     * @class
     */
    var lzjs = {
        /**
         * @lends lzjs
         */
        /**
         * Compress data.
         *
         * @param {string} data Input data
         * @param {Object=} [options] Options
         * @return {string} Compressed data
         */
        compress: function(data, options) {
            return new LZJS(options).compress(data);
        },
        /**
         * Decompress data.
         *
         * @param {string} data Input data
         * @param {Object=} [options] Options
         * @return {string} Decompressed data
         */
        decompress: function(data, options) {
            return new LZJS(options).decompress(data);
        },
        /**
         * Compress data to base64 string.
         *
         * @param {string} data Input data
         * @param {Object=} [options] Options
         * @return {string} Compressed data
         */
        compressToBase64: function(data, options) {
            return base64encode(toUTF8(new LZJS(options).compress(data)));
        },
        /**
         * Decompress data from base64 string.
         *
         * @param {string} data Input data
         * @param {Object=} [options] Options
         * @return {string} Decompressed data
         */
        decompressFromBase64: function(data, options) {
            return new LZJS(options).decompress(toUTF16(base64decode(data)));
        }
    };

    return lzjs;
}));