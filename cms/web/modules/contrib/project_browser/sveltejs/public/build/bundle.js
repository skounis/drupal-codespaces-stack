(function () {
    'use strict';

    function noop() { }
    function assign(tar, src) {
        // @ts-ignore
        for (const k in src)
            tar[k] = src[k];
        return tar;
    }
    function run(fn) {
        return fn();
    }
    function blank_object() {
        return Object.create(null);
    }
    function run_all(fns) {
        fns.forEach(run);
    }
    function is_function(thing) {
        return typeof thing === 'function';
    }
    function safe_not_equal(a, b) {
        return a != a ? b == b : a !== b || ((a && typeof a === 'object') || typeof a === 'function');
    }
    let src_url_equal_anchor;
    function src_url_equal(element_src, url) {
        if (!src_url_equal_anchor) {
            src_url_equal_anchor = document.createElement('a');
        }
        src_url_equal_anchor.href = url;
        return element_src === src_url_equal_anchor.href;
    }
    function is_empty(obj) {
        return Object.keys(obj).length === 0;
    }
    function subscribe(store, ...callbacks) {
        if (store == null) {
            return noop;
        }
        const unsub = store.subscribe(...callbacks);
        return unsub.unsubscribe ? () => unsub.unsubscribe() : unsub;
    }
    function get_store_value(store) {
        let value;
        subscribe(store, _ => value = _)();
        return value;
    }
    function component_subscribe(component, store, callback) {
        component.$$.on_destroy.push(subscribe(store, callback));
    }
    function create_slot(definition, ctx, $$scope, fn) {
        if (definition) {
            const slot_ctx = get_slot_context(definition, ctx, $$scope, fn);
            return definition[0](slot_ctx);
        }
    }
    function get_slot_context(definition, ctx, $$scope, fn) {
        return definition[1] && fn
            ? assign($$scope.ctx.slice(), definition[1](fn(ctx)))
            : $$scope.ctx;
    }
    function get_slot_changes(definition, $$scope, dirty, fn) {
        if (definition[2] && fn) {
            const lets = definition[2](fn(dirty));
            if ($$scope.dirty === undefined) {
                return lets;
            }
            if (typeof lets === 'object') {
                const merged = [];
                const len = Math.max($$scope.dirty.length, lets.length);
                for (let i = 0; i < len; i += 1) {
                    merged[i] = $$scope.dirty[i] | lets[i];
                }
                return merged;
            }
            return $$scope.dirty | lets;
        }
        return $$scope.dirty;
    }
    function update_slot_base(slot, slot_definition, ctx, $$scope, slot_changes, get_slot_context_fn) {
        if (slot_changes) {
            const slot_context = get_slot_context(slot_definition, ctx, $$scope, get_slot_context_fn);
            slot.p(slot_context, slot_changes);
        }
    }
    function get_all_dirty_from_scope($$scope) {
        if ($$scope.ctx.length > 32) {
            const dirty = [];
            const length = $$scope.ctx.length / 32;
            for (let i = 0; i < length; i++) {
                dirty[i] = -1;
            }
            return dirty;
        }
        return -1;
    }
    function exclude_internal_props(props) {
        const result = {};
        for (const k in props)
            if (k[0] !== '$')
                result[k] = props[k];
        return result;
    }
    function compute_rest_props(props, keys) {
        const rest = {};
        keys = new Set(keys);
        for (const k in props)
            if (!keys.has(k) && k[0] !== '$')
                rest[k] = props[k];
        return rest;
    }
    function set_store_value(store, ret, value) {
        store.set(value);
        return ret;
    }
    function append(target, node) {
        target.appendChild(node);
    }
    function insert(target, node, anchor) {
        target.insertBefore(node, anchor || null);
    }
    function detach(node) {
        node.parentNode.removeChild(node);
    }
    function destroy_each(iterations, detaching) {
        for (let i = 0; i < iterations.length; i += 1) {
            if (iterations[i])
                iterations[i].d(detaching);
        }
    }
    function element(name) {
        return document.createElement(name);
    }
    function svg_element(name) {
        return document.createElementNS('http://www.w3.org/2000/svg', name);
    }
    function text(data) {
        return document.createTextNode(data);
    }
    function space() {
        return text(' ');
    }
    function empty() {
        return text('');
    }
    function listen(node, event, handler, options) {
        node.addEventListener(event, handler, options);
        return () => node.removeEventListener(event, handler, options);
    }
    function prevent_default(fn) {
        return function (event) {
            event.preventDefault();
            // @ts-ignore
            return fn.call(this, event);
        };
    }
    function attr(node, attribute, value) {
        if (value == null)
            node.removeAttribute(attribute);
        else if (node.getAttribute(attribute) !== value)
            node.setAttribute(attribute, value);
    }
    function set_attributes(node, attributes) {
        // @ts-ignore
        const descriptors = Object.getOwnPropertyDescriptors(node.__proto__);
        for (const key in attributes) {
            if (attributes[key] == null) {
                node.removeAttribute(key);
            }
            else if (key === 'style') {
                node.style.cssText = attributes[key];
            }
            else if (key === '__value') {
                node.value = node[key] = attributes[key];
            }
            else if (descriptors[key] && descriptors[key].set) {
                node[key] = attributes[key];
            }
            else {
                attr(node, key, attributes[key]);
            }
        }
    }
    function get_binding_group_value(group, __value, checked) {
        const value = new Set();
        for (let i = 0; i < group.length; i += 1) {
            if (group[i].checked)
                value.add(group[i].__value);
        }
        if (!checked) {
            value.delete(__value);
        }
        return Array.from(value);
    }
    function children(element) {
        return Array.from(element.childNodes);
    }
    function set_data(text, data) {
        data = '' + data;
        if (text.wholeText !== data)
            text.data = data;
    }
    function set_input_value(input, value) {
        input.value = value == null ? '' : value;
    }
    function select_option(select, value) {
        for (let i = 0; i < select.options.length; i += 1) {
            const option = select.options[i];
            if (option.__value === value) {
                option.selected = true;
                return;
            }
        }
        select.selectedIndex = -1; // no option should be selected
    }
    function select_value(select) {
        const selected_option = select.querySelector(':checked') || select.options[0];
        return selected_option && selected_option.__value;
    }
    function toggle_class(element, name, toggle) {
        element.classList[toggle ? 'add' : 'remove'](name);
    }
    function custom_event(type, detail, { bubbles = false, cancelable = false } = {}) {
        const e = document.createEvent('CustomEvent');
        e.initCustomEvent(type, bubbles, cancelable, detail);
        return e;
    }
    class HtmlTag {
        constructor(is_svg = false) {
            this.is_svg = false;
            this.is_svg = is_svg;
            this.e = this.n = null;
        }
        c(html) {
            this.h(html);
        }
        m(html, target, anchor = null) {
            if (!this.e) {
                if (this.is_svg)
                    this.e = svg_element(target.nodeName);
                else
                    this.e = element(target.nodeName);
                this.t = target;
                this.c(html);
            }
            this.i(anchor);
        }
        h(html) {
            this.e.innerHTML = html;
            this.n = Array.from(this.e.childNodes);
        }
        i(anchor) {
            for (let i = 0; i < this.n.length; i += 1) {
                insert(this.t, this.n[i], anchor);
            }
        }
        p(html) {
            this.d();
            this.h(html);
            this.i(this.a);
        }
        d() {
            this.n.forEach(detach);
        }
    }

    let current_component;
    function set_current_component(component) {
        current_component = component;
    }
    function get_current_component() {
        if (!current_component)
            throw new Error('Function called outside component initialization');
        return current_component;
    }
    function onMount(fn) {
        get_current_component().$$.on_mount.push(fn);
    }
    function afterUpdate(fn) {
        get_current_component().$$.after_update.push(fn);
    }
    function createEventDispatcher() {
        const component = get_current_component();
        return (type, detail, { cancelable = false } = {}) => {
            const callbacks = component.$$.callbacks[type];
            if (callbacks) {
                // TODO are there situations where events could be dispatched
                // in a server (non-DOM) environment?
                const event = custom_event(type, detail, { cancelable });
                callbacks.slice().forEach(fn => {
                    fn.call(component, event);
                });
                return !event.defaultPrevented;
            }
            return true;
        };
    }
    function setContext(key, context) {
        get_current_component().$$.context.set(key, context);
        return context;
    }
    function getContext(key) {
        return get_current_component().$$.context.get(key);
    }
    // TODO figure out if we still want to support
    // shorthand events, or if we want to implement
    // a real bubbling mechanism
    function bubble(component, event) {
        const callbacks = component.$$.callbacks[event.type];
        if (callbacks) {
            // @ts-ignore
            callbacks.slice().forEach(fn => fn.call(this, event));
        }
    }

    const dirty_components = [];
    const binding_callbacks = [];
    const render_callbacks = [];
    const flush_callbacks = [];
    const resolved_promise = Promise.resolve();
    let update_scheduled = false;
    function schedule_update() {
        if (!update_scheduled) {
            update_scheduled = true;
            resolved_promise.then(flush);
        }
    }
    function add_render_callback(fn) {
        render_callbacks.push(fn);
    }
    function add_flush_callback(fn) {
        flush_callbacks.push(fn);
    }
    // flush() calls callbacks in this order:
    // 1. All beforeUpdate callbacks, in order: parents before children
    // 2. All bind:this callbacks, in reverse order: children before parents.
    // 3. All afterUpdate callbacks, in order: parents before children. EXCEPT
    //    for afterUpdates called during the initial onMount, which are called in
    //    reverse order: children before parents.
    // Since callbacks might update component values, which could trigger another
    // call to flush(), the following steps guard against this:
    // 1. During beforeUpdate, any updated components will be added to the
    //    dirty_components array and will cause a reentrant call to flush(). Because
    //    the flush index is kept outside the function, the reentrant call will pick
    //    up where the earlier call left off and go through all dirty components. The
    //    current_component value is saved and restored so that the reentrant call will
    //    not interfere with the "parent" flush() call.
    // 2. bind:this callbacks cannot trigger new flush() calls.
    // 3. During afterUpdate, any updated components will NOT have their afterUpdate
    //    callback called a second time; the seen_callbacks set, outside the flush()
    //    function, guarantees this behavior.
    const seen_callbacks = new Set();
    let flushidx = 0; // Do *not* move this inside the flush() function
    function flush() {
        const saved_component = current_component;
        do {
            // first, call beforeUpdate functions
            // and update components
            while (flushidx < dirty_components.length) {
                const component = dirty_components[flushidx];
                flushidx++;
                set_current_component(component);
                update(component.$$);
            }
            set_current_component(null);
            dirty_components.length = 0;
            flushidx = 0;
            while (binding_callbacks.length)
                binding_callbacks.pop()();
            // then, once components are updated, call
            // afterUpdate functions. This may cause
            // subsequent updates...
            for (let i = 0; i < render_callbacks.length; i += 1) {
                const callback = render_callbacks[i];
                if (!seen_callbacks.has(callback)) {
                    // ...so guard against infinite loops
                    seen_callbacks.add(callback);
                    callback();
                }
            }
            render_callbacks.length = 0;
        } while (dirty_components.length);
        while (flush_callbacks.length) {
            flush_callbacks.pop()();
        }
        update_scheduled = false;
        seen_callbacks.clear();
        set_current_component(saved_component);
    }
    function update($$) {
        if ($$.fragment !== null) {
            $$.update();
            run_all($$.before_update);
            const dirty = $$.dirty;
            $$.dirty = [-1];
            $$.fragment && $$.fragment.p($$.ctx, dirty);
            $$.after_update.forEach(add_render_callback);
        }
    }
    const outroing = new Set();
    let outros;
    function group_outros() {
        outros = {
            r: 0,
            c: [],
            p: outros // parent group
        };
    }
    function check_outros() {
        if (!outros.r) {
            run_all(outros.c);
        }
        outros = outros.p;
    }
    function transition_in(block, local) {
        if (block && block.i) {
            outroing.delete(block);
            block.i(local);
        }
    }
    function transition_out(block, local, detach, callback) {
        if (block && block.o) {
            if (outroing.has(block))
                return;
            outroing.add(block);
            outros.c.push(() => {
                outroing.delete(block);
                if (callback) {
                    if (detach)
                        block.d(1);
                    callback();
                }
            });
            block.o(local);
        }
    }

    const globals = (typeof window !== 'undefined'
        ? window
        : typeof globalThis !== 'undefined'
            ? globalThis
            : global);
    function outro_and_destroy_block(block, lookup) {
        transition_out(block, 1, 1, () => {
            lookup.delete(block.key);
        });
    }
    function update_keyed_each(old_blocks, dirty, get_key, dynamic, ctx, list, lookup, node, destroy, create_each_block, next, get_context) {
        let o = old_blocks.length;
        let n = list.length;
        let i = o;
        const old_indexes = {};
        while (i--)
            old_indexes[old_blocks[i].key] = i;
        const new_blocks = [];
        const new_lookup = new Map();
        const deltas = new Map();
        i = n;
        while (i--) {
            const child_ctx = get_context(ctx, list, i);
            const key = get_key(child_ctx);
            let block = lookup.get(key);
            if (!block) {
                block = create_each_block(key, child_ctx);
                block.c();
            }
            else if (dynamic) {
                block.p(child_ctx, dirty);
            }
            new_lookup.set(key, new_blocks[i] = block);
            if (key in old_indexes)
                deltas.set(key, Math.abs(i - old_indexes[key]));
        }
        const will_move = new Set();
        const did_move = new Set();
        function insert(block) {
            transition_in(block, 1);
            block.m(node, next);
            lookup.set(block.key, block);
            next = block.first;
            n--;
        }
        while (o && n) {
            const new_block = new_blocks[n - 1];
            const old_block = old_blocks[o - 1];
            const new_key = new_block.key;
            const old_key = old_block.key;
            if (new_block === old_block) {
                // do nothing
                next = new_block.first;
                o--;
                n--;
            }
            else if (!new_lookup.has(old_key)) {
                // remove old block
                destroy(old_block, lookup);
                o--;
            }
            else if (!lookup.has(new_key) || will_move.has(new_key)) {
                insert(new_block);
            }
            else if (did_move.has(old_key)) {
                o--;
            }
            else if (deltas.get(new_key) > deltas.get(old_key)) {
                did_move.add(new_key);
                insert(new_block);
            }
            else {
                will_move.add(old_key);
                o--;
            }
        }
        while (o--) {
            const old_block = old_blocks[o];
            if (!new_lookup.has(old_block.key))
                destroy(old_block, lookup);
        }
        while (n)
            insert(new_blocks[n - 1]);
        return new_blocks;
    }

    function get_spread_update(levels, updates) {
        const update = {};
        const to_null_out = {};
        const accounted_for = { $$scope: 1 };
        let i = levels.length;
        while (i--) {
            const o = levels[i];
            const n = updates[i];
            if (n) {
                for (const key in o) {
                    if (!(key in n))
                        to_null_out[key] = 1;
                }
                for (const key in n) {
                    if (!accounted_for[key]) {
                        update[key] = n[key];
                        accounted_for[key] = 1;
                    }
                }
                levels[i] = n;
            }
            else {
                for (const key in o) {
                    accounted_for[key] = 1;
                }
            }
        }
        for (const key in to_null_out) {
            if (!(key in update))
                update[key] = undefined;
        }
        return update;
    }

    function bind(component, name, callback) {
        const index = component.$$.props[name];
        if (index !== undefined) {
            component.$$.bound[index] = callback;
            callback(component.$$.ctx[index]);
        }
    }
    function create_component(block) {
        block && block.c();
    }
    function mount_component(component, target, anchor, customElement) {
        const { fragment, on_mount, on_destroy, after_update } = component.$$;
        fragment && fragment.m(target, anchor);
        if (!customElement) {
            // onMount happens before the initial afterUpdate
            add_render_callback(() => {
                const new_on_destroy = on_mount.map(run).filter(is_function);
                if (on_destroy) {
                    on_destroy.push(...new_on_destroy);
                }
                else {
                    // Edge case - component was destroyed immediately,
                    // most likely as a result of a binding initialising
                    run_all(new_on_destroy);
                }
                component.$$.on_mount = [];
            });
        }
        after_update.forEach(add_render_callback);
    }
    function destroy_component(component, detaching) {
        const $$ = component.$$;
        if ($$.fragment !== null) {
            run_all($$.on_destroy);
            $$.fragment && $$.fragment.d(detaching);
            // TODO null out other refs, including component.$$ (but need to
            // preserve final state?)
            $$.on_destroy = $$.fragment = null;
            $$.ctx = [];
        }
    }
    function make_dirty(component, i) {
        if (component.$$.dirty[0] === -1) {
            dirty_components.push(component);
            schedule_update();
            component.$$.dirty.fill(0);
        }
        component.$$.dirty[(i / 31) | 0] |= (1 << (i % 31));
    }
    function init(component, options, instance, create_fragment, not_equal, props, append_styles, dirty = [-1]) {
        const parent_component = current_component;
        set_current_component(component);
        const $$ = component.$$ = {
            fragment: null,
            ctx: null,
            // state
            props,
            update: noop,
            not_equal,
            bound: blank_object(),
            // lifecycle
            on_mount: [],
            on_destroy: [],
            on_disconnect: [],
            before_update: [],
            after_update: [],
            context: new Map(options.context || (parent_component ? parent_component.$$.context : [])),
            // everything else
            callbacks: blank_object(),
            dirty,
            skip_bound: false,
            root: options.target || parent_component.$$.root
        };
        append_styles && append_styles($$.root);
        let ready = false;
        $$.ctx = instance
            ? instance(component, options.props || {}, (i, ret, ...rest) => {
                const value = rest.length ? rest[0] : ret;
                if ($$.ctx && not_equal($$.ctx[i], $$.ctx[i] = value)) {
                    if (!$$.skip_bound && $$.bound[i])
                        $$.bound[i](value);
                    if (ready)
                        make_dirty(component, i);
                }
                return ret;
            })
            : [];
        $$.update();
        ready = true;
        run_all($$.before_update);
        // `false` as a special case of no DOM component
        $$.fragment = create_fragment ? create_fragment($$.ctx) : false;
        if (options.target) {
            if (options.hydrate) {
                const nodes = children(options.target);
                // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
                $$.fragment && $$.fragment.l(nodes);
                nodes.forEach(detach);
            }
            else {
                // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
                $$.fragment && $$.fragment.c();
            }
            if (options.intro)
                transition_in(component.$$.fragment);
            mount_component(component, options.target, options.anchor, options.customElement);
            flush();
        }
        set_current_component(parent_component);
    }
    /**
     * Base class for Svelte components. Used when dev=false.
     */
    class SvelteComponent {
        $destroy() {
            destroy_component(this, 1);
            this.$destroy = noop;
        }
        $on(type, callback) {
            const callbacks = (this.$$.callbacks[type] || (this.$$.callbacks[type] = []));
            callbacks.push(callback);
            return () => {
                const index = callbacks.indexOf(callback);
                if (index !== -1)
                    callbacks.splice(index, 1);
            };
        }
        $set($$props) {
            if (this.$$set && !is_empty($$props)) {
                this.$$.skip_bound = true;
                this.$$set($$props);
                this.$$.skip_bound = false;
            }
        }
    }

    const subscriber_queue = [];
    /**
     * Creates a `Readable` store that allows reading by subscription.
     * @param value initial value
     * @param {StartStopNotifier}start start and stop notifications for subscriptions
     */
    function readable(value, start) {
        return {
            subscribe: writable(value, start).subscribe
        };
    }
    /**
     * Create a `Writable` store that allows both updating and reading by subscription.
     * @param {*=}value initial value
     * @param {StartStopNotifier=}start start and stop notifications for subscriptions
     */
    function writable(value, start = noop) {
        let stop;
        const subscribers = new Set();
        function set(new_value) {
            if (safe_not_equal(value, new_value)) {
                value = new_value;
                if (stop) { // store is ready
                    const run_queue = !subscriber_queue.length;
                    for (const subscriber of subscribers) {
                        subscriber[1]();
                        subscriber_queue.push(subscriber, value);
                    }
                    if (run_queue) {
                        for (let i = 0; i < subscriber_queue.length; i += 2) {
                            subscriber_queue[i][0](subscriber_queue[i + 1]);
                        }
                        subscriber_queue.length = 0;
                    }
                }
            }
        }
        function update(fn) {
            set(fn(value));
        }
        function subscribe(run, invalidate = noop) {
            const subscriber = [run, invalidate];
            subscribers.add(subscriber);
            if (subscribers.size === 1) {
                stop = start(set) || noop;
            }
            run(value);
            return () => {
                subscribers.delete(subscriber);
                if (subscribers.size === 0) {
                    stop();
                    stop = null;
                }
            };
        }
        return { set, update, subscribe };
    }
    function derived(stores, fn, initial_value) {
        const single = !Array.isArray(stores);
        const stores_array = single
            ? [stores]
            : stores;
        const auto = fn.length < 2;
        return readable(initial_value, (set) => {
            let inited = false;
            const values = [];
            let pending = 0;
            let cleanup = noop;
            const sync = () => {
                if (pending) {
                    return;
                }
                cleanup();
                const result = fn(single ? values[0] : values, set);
                if (auto) {
                    set(result);
                }
                else {
                    cleanup = is_function(result) ? result : noop;
                }
            };
            const unsubscribers = stores_array.map((store, i) => subscribe(store, (value) => {
                values[i] = value;
                pending &= ~(1 << i);
                if (inited) {
                    sync();
                }
            }, () => {
                pending |= (1 << i);
            }));
            inited = true;
            sync();
            return function stop() {
                run_all(unsubscribers);
                cleanup();
            };
        });
    }

    /*! *****************************************************************************
    Copyright (c) Microsoft Corporation.

    Permission to use, copy, modify, and/or distribute this software for any
    purpose with or without fee is hereby granted.

    THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH
    REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT,
    INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM
    LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR
    OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR
    PERFORMANCE OF THIS SOFTWARE.
    ***************************************************************************** */

    function __spreadArray(to, from, pack) {
        if (pack || arguments.length === 2) for (var i = 0, l = from.length, ar; i < l; i++) {
            if (ar || !(i in from)) {
                if (!ar) ar = Array.prototype.slice.call(from, 0, i);
                ar[i] = from[i];
            }
        }
        return to.concat(ar || Array.prototype.slice.call(from));
    }

    function withPrevious(initValue, _a) {
        var _b = _a === void 0 ? {} : _a, _c = _b.numToTrack, numToTrack = _c === void 0 ? 1 : _c, _d = _b.initPrevious, initPrevious = _d === void 0 ? [] : _d, _e = _b.requireChange, requireChange = _e === void 0 ? true : _e, _f = _b.isEqual, isEqual = _f === void 0 ? function (a, b) { return a === b; } : _f;
        if (numToTrack <= 0) {
            throw new Error('Must track at least 1 previous');
        }
        // Generates an array of size numToTrack with the first element set to
        // initValue and all other elements set to ...initPrevious or null.
        var rest = initPrevious.slice(0, numToTrack);
        while (rest.length < numToTrack) {
            rest.push(null);
        }
        var values = writable(__spreadArray([initValue], rest, true));
        var updateCurrent = function (fn) {
            values.update(function ($values) {
                var newValue = fn($values[0]);
                // Prevent updates if values are equal as defined by an isEqual
                // comparison. By default, use a simple === comparison.
                if (requireChange && isEqual(newValue, $values[0])) {
                    return $values;
                }
                // Adds the new value to the front of the array and removes the oldest
                // value from the end.
                return __spreadArray([newValue], $values.slice(0, numToTrack), true);
            });
        };
        var current = {
            subscribe: derived(values, function ($values) { return $values[0]; }).subscribe,
            update: updateCurrent,
            set: function (newValue) {
                updateCurrent(function () { return newValue; });
            },
        };
        // Create an array of derived stores for every other element in the array.
        var others = __spreadArray([], Array(numToTrack), true).map(function (_, i) {
            return derived(values, function ($values) { return $values[i + 1]; });
        });
        return __spreadArray([current], others, true);
    }

    // cspell:ignore dont
    const { once, Drupal: Drupal$2, bodyScrollLock } = window;

    /**
     * Finds [data-copy-command] buttons and adds copy functionality to them.
     */
    const enableCopyButtons = () => {
      setTimeout(() => {
        once('copyButton', '[data-copy-command]').forEach((copyButton) => {
          copyButton.addEventListener('click', (e) => {
            // The copy button must be contained in a div
            const container = e.target.closest('div');
            // The only <textarea> within the parent div should have its value set
            // to the command that should be copied.
            const input = container.querySelector('textarea');

            // Make the input value the selected text
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value);
            Drupal$2.announce(Drupal$2.t('Copied text to clipboard'));

            // Create a "receipt" that will visually show the text has been copied.
            const receipt = document.createElement('div');
            receipt.textContent = Drupal$2.t('Copied');
            receipt.classList.add('copied-action');
            receipt.style.opacity = '1';
            input.insertAdjacentElement('afterend', receipt);
            // eslint-disable-next-line max-nested-callbacks
            setTimeout(() => {
              // Remove the receipt after 1 second.
              receipt.remove();
            }, 1000);
          });
        });
      });
    };

    const getCommandsPopupMessage = (project) => {
      const div = document.createElement('div');
      div.innerHTML = project.commands + '<style>.action-link { margin: 0 2px; padding: 0.25rem 0.25rem; border: 1px solid; }</style>';
      enableCopyButtons();
      return div;
    };

    const openPopup = (getMessage, project) => {
      const message = typeof getMessage === 'function' ? getMessage() : getMessage;
      const isModuleDetail = getMessage.firstElementChild.classList.contains('pb-detail-modal');

      const popupModal = Drupal$2.dialog(message, {
        title: project.title,
        classes: { 'ui-dialog': isModuleDetail ? 'project-browser-detail-modal' : 'project-browser-popup' },
        width: '90vw',
        close: () => {
          document.querySelector('.ui-dialog').remove();
          bodyScrollLock.clearBodyLocks();
        }
      });
      popupModal.showModal();
      const modalElement = document.querySelector('.project-browser-detail-modal');
      if (modalElement) {
        modalElement.focus();
      }
    };

    const BASE_URL = `${window.location.protocol}//${window.location.host}${drupalSettings.path.baseUrl + drupalSettings.path.pathPrefix}`;
    const FULL_MODULE_PATH = `${BASE_URL}${drupalSettings.project_browser.module_path}`;
    const DARK_COLOR_SCHEME =
      matchMedia('(forced-colors: active)').matches &&
      matchMedia('(prefers-color-scheme: dark)').matches;
    const PACKAGE_MANAGER = drupalSettings.project_browser.package_manager;
    const MAX_SELECTIONS = drupalSettings.project_browser.max_selections;
    const CURRENT_PATH = drupalSettings.project_browser.current_path;

    const { Drupal: Drupal$1 } = window;

    // Store for the install list.
    const installList = writable([]);

    function addToInstallList(project) {
      installList.update((currentList) => {
        if (!currentList.includes(project)) {
          currentList.push(project);
        }
        return currentList;
      });
    }

    function removeFromInstallList(projectId) {
      installList.update((currentList) => currentList.filter(
          (item) => item.id !== projectId,
        ));
    }

    function clearInstallList() {
      installList.set([]);
    }

    const handleError = async (errorResponse) => {
      // The error can take on many shapes, so it should be normalized.
      let err = '';
      if (typeof errorResponse === 'string') {
        err = errorResponse;
      } else {
        err = await errorResponse.text();
      }
      try {
        // See if the error string can be parsed as JSON. If not, the block
        // is exited before the `err` string is overwritten.
        const parsed = JSON.parse(err);
        err = parsed;
      } catch {
        // The catch behavior is established before the try block.
      }

      const errorMessage = err.message || err;

      // The popup function expects an element, so a div containing the error
      // message is created here for it to display in a modal.
      const div = document.createElement('div');

      const currentUrl =
        window.location.pathname + window.location.search + window.location.hash;

      if (err.unlock_url) {
        try {
          const unlockUrl = new URL(err.unlock_url, BASE_URL);
          unlockUrl.searchParams.set('destination', currentUrl);

          const updatedMessage = errorMessage.replace(
            '[+ unlock link]',
            `<a href="${
          unlockUrl.pathname + unlockUrl.search
        }" id="unlock-link">${Drupal$1.t('unlock link')}</a>`,
          );

          div.innerHTML += `<p>${updatedMessage}</p>`;
        } catch {
          div.innerHTML += `<p>${errorMessage}</p>`;
        }
      } else {
        div.innerHTML += `<p>${errorMessage}</p>`;
      }

      openPopup(div, { title: 'Error while installing package(s)' });
    };

    /**
     * Actives already-downloaded projects.
     *
     * @param {string[]} projectIds
     *   An array of project IDs to activate.
     *
     * @return {Promise<void>}
     *   A promise that resolves when the project is activated.
     */
    const activateProject = async (projectIds) => {
      // Remove any existing errors for each project individually.
      const messenger = new Drupal$1.Message();
      projectIds.forEach((projectId) => {
        const messageId = `activation_error:${projectId}`;
        if (messenger.select(messageId)) {
          messenger.remove(messageId);
        }
      });

      await new Drupal$1.Ajax(
        null,
        document.createElement('div'),
        {
          url: `${BASE_URL}admin/modules/project_browser/activate?projects=${projectIds.join(',')}`,
        },
      ).execute();
    };

    /**
     * Performs the requests necessary to download and activate project via Package Manager.
     *
     * @param {string[]} projectIds
     *   An array of project IDs to download and activate.
     *
     * @return {Promise<void>}
     *   Returns a promise that resolves once the download and activation process is complete.
     */
    const doRequests = async (projectIds) => {
      const beginInstallUrl = `${BASE_URL}admin/modules/project_browser/install-begin?redirect=${
    CURRENT_PATH
  }`;
      const beginInstallResponse = await fetch(beginInstallUrl);
      if (!beginInstallResponse.ok) {
        await handleError(beginInstallResponse);
      } else {
        const beginInstallData = await beginInstallResponse.json();
        const stageId = beginInstallData.stage_id;

        // The process of adding a module is separated into four stages, each
        // with their own endpoint. When one stage completes, the next one is
        // requested.
        const installSteps = [
          {
            url: `${BASE_URL}admin/modules/project_browser/install-require/${stageId}`,
            method: 'POST',
          },
          {
            url: `${BASE_URL}admin/modules/project_browser/install-apply/${stageId}`,
            method: 'GET',
          },
          {
            url: `${BASE_URL}admin/modules/project_browser/install-post_apply/${stageId}`,
            method: 'GET',
          },
          {
            url: `${BASE_URL}admin/modules/project_browser/install-destroy/${stageId}`,
            method: 'GET',
          },
        ];

        // eslint-disable-next-line no-restricted-syntax,guard-for-in
        for (const step of installSteps) {
          const options = {
            method: step.method,
          };

          // Additional options need to be added when the request method is POST.
          // This is specifically required for the `install-require` step.
          if (step.method === 'POST') {
            options.headers = {
              'Content-Type': 'application/json',
            };

            // Set the request body to include the project(s) id as an array.
            options.body = JSON.stringify(projectIds);
          }
          // eslint-disable-next-line no-await-in-loop
          const stepResponse = await fetch(step.url, options);
          if (!stepResponse.ok) {
            // eslint-disable-next-line no-await-in-loop
            const errorMessage = await stepResponse.text();
            // eslint-disable-next-line no-console
            console.warn(
              `failed request to ${step.url}: ${errorMessage}`,
              stepResponse,
            );
            // eslint-disable-next-line no-await-in-loop
            await handleError(errorMessage);
            return;
          }
        }
        await activateProject(projectIds);
      }
    };

    const processInstallList = async () => {
      const currentInstallList = get_store_value(installList) || [];
      const projectsToActivate = [];
      const projectsToDownloadAndActivate = [];
      if (currentInstallList.length === 0) {
        const messageElement = document.querySelector('[data-drupal-message-id="install_message"]');

        if (!messageElement) {
          // If the message does not exist, create a new one.
          new Drupal$1.Message().add(Drupal$1.t('No projects selected'), { type: 'error', id: 'install_message' });
        } else if (messageElement.classList.contains('visually-hidden')) {
          // If the message exists but is visually hidden, remove the class and reset opacity.
          messageElement.classList.remove('visually-hidden');
          messageElement.style.opacity = 1;
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
      }

      for (const proj of currentInstallList) {
        if (proj.status === 'absent') {
          projectsToDownloadAndActivate.push(proj.id);
        } else if (proj.status === 'present') {
          projectsToActivate.push(proj.id);
        }
      }

      document.body.style.pointerEvents = 'none';

      if (projectsToActivate.length > 0) {
        await activateProject(projectsToActivate);
      }
      if (projectsToDownloadAndActivate.length > 0) {
        await doRequests(projectsToDownloadAndActivate);
      }

      document.body.style.pointerEvents = 'auto';

      clearInstallList();
    };

    /* src/Loading.svelte generated by Svelte v3.48.0 */

    function create_fragment$o(ctx) {
    	let div1;
    	let div0;

    	return {
    		c() {
    			div1 = element("div");
    			div0 = element("div");
    			div0.textContent = " ";
    			attr(div0, "class", "ajax-progress__throbber");
    			toggle_class(div0, "ajax-progress__throbber--fullscreen", !/*inline*/ ctx[1]);
    			attr(div1, "class", "loading__ajax-progress");
    			toggle_class(div1, "absolute", /*positionAbsolute*/ ctx[0]);
    			toggle_class(div1, "ajax-progress--fullscreen", !/*inline*/ ctx[1]);
    		},
    		m(target, anchor) {
    			insert(target, div1, anchor);
    			append(div1, div0);
    		},
    		p(ctx, [dirty]) {
    			if (dirty & /*inline*/ 2) {
    				toggle_class(div0, "ajax-progress__throbber--fullscreen", !/*inline*/ ctx[1]);
    			}

    			if (dirty & /*positionAbsolute*/ 1) {
    				toggle_class(div1, "absolute", /*positionAbsolute*/ ctx[0]);
    			}

    			if (dirty & /*inline*/ 2) {
    				toggle_class(div1, "ajax-progress--fullscreen", !/*inline*/ ctx[1]);
    			}
    		},
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if (detaching) detach(div1);
    		}
    	};
    }

    function instance$o($$self, $$props, $$invalidate) {
    	let { positionAbsolute = false } = $$props;
    	let { inline = false } = $$props;

    	$$self.$$set = $$props => {
    		if ('positionAbsolute' in $$props) $$invalidate(0, positionAbsolute = $$props.positionAbsolute);
    		if ('inline' in $$props) $$invalidate(1, inline = $$props.inline);
    	};

    	return [positionAbsolute, inline];
    }

    class Loading extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$o, create_fragment$o, safe_not_equal, { positionAbsolute: 0, inline: 1 });
    	}
    }

    /* src/Project/LoadingEllipsis.svelte generated by Svelte v3.48.0 */

    function create_fragment$n(ctx) {
    	let span;
    	let t;

    	return {
    		c() {
    			span = element("span");
    			t = text(/*message*/ ctx[0]);
    			toggle_class(span, "pb-ellipsis", MAX_SELECTIONS === 1);
    		},
    		m(target, anchor) {
    			insert(target, span, anchor);
    			append(span, t);
    		},
    		p(ctx, [dirty]) {
    			if (dirty & /*message*/ 1) set_data(t, /*message*/ ctx[0]);
    		},
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if (detaching) detach(span);
    		}
    	};
    }

    function instance$n($$self, $$props, $$invalidate) {
    	let { message = window.Drupal.t('Installing') } = $$props;

    	$$self.$$set = $$props => {
    		if ('message' in $$props) $$invalidate(0, message = $$props.message);
    	};

    	return [message];
    }

    class LoadingEllipsis extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$n, create_fragment$n, safe_not_equal, { message: 0 });
    	}
    }

    /* src/ProcessInstallListButton.svelte generated by Svelte v3.48.0 */

    function create_else_block_1$1(ctx) {
    	let t_value = /*Drupal*/ ctx[2].formatPlural(/*installListLength*/ ctx[1], '1 project selected', '@count projects selected') + "";
    	let t;

    	return {
    		c() {
    			t = text(t_value);
    		},
    		m(target, anchor) {
    			insert(target, t, anchor);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*installListLength*/ 2 && t_value !== (t_value = /*Drupal*/ ctx[2].formatPlural(/*installListLength*/ ctx[1], '1 project selected', '@count projects selected') + "")) set_data(t, t_value);
    		},
    		d(detaching) {
    			if (detaching) detach(t);
    		}
    	};
    }

    // (40:4) {#if installListLength === 0}
    function create_if_block_2$6(ctx) {
    	let t_value = window.Drupal.t('No projects selected') + "";
    	let t;

    	return {
    		c() {
    			t = text(t_value);
    		},
    		m(target, anchor) {
    			insert(target, t, anchor);
    		},
    		p: noop,
    		d(detaching) {
    			if (detaching) detach(t);
    		}
    	};
    }

    // (63:4) {:else}
    function create_else_block$6(ctx) {
    	let t_value = window.Drupal.t('Install selected projects') + "";
    	let t;

    	return {
    		c() {
    			t = text(t_value);
    		},
    		m(target, anchor) {
    			insert(target, t, anchor);
    		},
    		p: noop,
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if (detaching) detach(t);
    		}
    	};
    }

    // (54:4) {#if loading}
    function create_if_block_1$9(ctx) {
    	let loading_1;
    	let t;
    	let loadingellipsis;
    	let current;
    	loading_1 = new Loading({});

    	loadingellipsis = new LoadingEllipsis({
    			props: {
    				message: /*Drupal*/ ctx[2].formatPlural(/*installListLength*/ ctx[1], 'Installing 1 project', 'Installing @count projects')
    			}
    		});

    	return {
    		c() {
    			create_component(loading_1.$$.fragment);
    			t = space();
    			create_component(loadingellipsis.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(loading_1, target, anchor);
    			insert(target, t, anchor);
    			mount_component(loadingellipsis, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const loadingellipsis_changes = {};
    			if (dirty & /*installListLength*/ 2) loadingellipsis_changes.message = /*Drupal*/ ctx[2].formatPlural(/*installListLength*/ ctx[1], 'Installing 1 project', 'Installing @count projects');
    			loadingellipsis.$set(loadingellipsis_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(loading_1.$$.fragment, local);
    			transition_in(loadingellipsis.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(loading_1.$$.fragment, local);
    			transition_out(loadingellipsis.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(loading_1, detaching);
    			if (detaching) detach(t);
    			destroy_component(loadingellipsis, detaching);
    		}
    	};
    }

    // (67:2) {#if installListLength !== 0}
    function create_if_block$f(ctx) {
    	let button;
    	let mounted;
    	let dispose;

    	return {
    		c() {
    			button = element("button");
    			button.textContent = `${window.Drupal.t('Clear selection')}`;
    			attr(button, "class", "button clear_button");
    		},
    		m(target, anchor) {
    			insert(target, button, anchor);

    			if (!mounted) {
    				dispose = listen(button, "click", /*clearSelection*/ ctx[4]);
    				mounted = true;
    			}
    		},
    		p: noop,
    		d(detaching) {
    			if (detaching) detach(button);
    			mounted = false;
    			dispose();
    		}
    	};
    }

    function create_fragment$m(ctx) {
    	let div1;
    	let div0;
    	let t0;
    	let button;
    	let current_block_type_index;
    	let if_block1;
    	let t1;
    	let div1_data_drupal_sticky_vbo_value;
    	let current;
    	let mounted;
    	let dispose;

    	function select_block_type(ctx, dirty) {
    		if (/*installListLength*/ ctx[1] === 0) return create_if_block_2$6;
    		return create_else_block_1$1;
    	}

    	let current_block_type = select_block_type(ctx);
    	let if_block0 = current_block_type(ctx);
    	const if_block_creators = [create_if_block_1$9, create_else_block$6];
    	const if_blocks = [];

    	function select_block_type_1(ctx, dirty) {
    		if (/*loading*/ ctx[0]) return 0;
    		return 1;
    	}

    	current_block_type_index = select_block_type_1(ctx);
    	if_block1 = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);
    	let if_block2 = /*installListLength*/ ctx[1] !== 0 && create_if_block$f(ctx);

    	return {
    		c() {
    			div1 = element("div");
    			div0 = element("div");
    			if_block0.c();
    			t0 = space();
    			button = element("button");
    			if_block1.c();
    			t1 = space();
    			if (if_block2) if_block2.c();
    			attr(div0, "class", "views-bulk-actions__item views-bulk-actions__item--status");
    			attr(button, "class", "project__action_button install_button_common install_button button--small button button--primary");
    			attr(div1, "class", "views-bulk-actions pb-install_bulk_actions");
    			attr(div1, "data-drupal-sticky-vbo", div1_data_drupal_sticky_vbo_value = /*installListLength*/ ctx[1] !== 0);
    		},
    		m(target, anchor) {
    			insert(target, div1, anchor);
    			append(div1, div0);
    			if_block0.m(div0, null);
    			append(div1, t0);
    			append(div1, button);
    			if_blocks[current_block_type_index].m(button, null);
    			append(div1, t1);
    			if (if_block2) if_block2.m(div1, null);
    			current = true;

    			if (!mounted) {
    				dispose = listen(button, "click", /*handleClick*/ ctx[3]);
    				mounted = true;
    			}
    		},
    		p(ctx, [dirty]) {
    			if (current_block_type === (current_block_type = select_block_type(ctx)) && if_block0) {
    				if_block0.p(ctx, dirty);
    			} else {
    				if_block0.d(1);
    				if_block0 = current_block_type(ctx);

    				if (if_block0) {
    					if_block0.c();
    					if_block0.m(div0, null);
    				}
    			}

    			let previous_block_index = current_block_type_index;
    			current_block_type_index = select_block_type_1(ctx);

    			if (current_block_type_index === previous_block_index) {
    				if_blocks[current_block_type_index].p(ctx, dirty);
    			} else {
    				group_outros();

    				transition_out(if_blocks[previous_block_index], 1, 1, () => {
    					if_blocks[previous_block_index] = null;
    				});

    				check_outros();
    				if_block1 = if_blocks[current_block_type_index];

    				if (!if_block1) {
    					if_block1 = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);
    					if_block1.c();
    				} else {
    					if_block1.p(ctx, dirty);
    				}

    				transition_in(if_block1, 1);
    				if_block1.m(button, null);
    			}

    			if (/*installListLength*/ ctx[1] !== 0) {
    				if (if_block2) {
    					if_block2.p(ctx, dirty);
    				} else {
    					if_block2 = create_if_block$f(ctx);
    					if_block2.c();
    					if_block2.m(div1, null);
    				}
    			} else if (if_block2) {
    				if_block2.d(1);
    				if_block2 = null;
    			}

    			if (!current || dirty & /*installListLength*/ 2 && div1_data_drupal_sticky_vbo_value !== (div1_data_drupal_sticky_vbo_value = /*installListLength*/ ctx[1] !== 0)) {
    				attr(div1, "data-drupal-sticky-vbo", div1_data_drupal_sticky_vbo_value);
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(if_block1);
    			current = true;
    		},
    		o(local) {
    			transition_out(if_block1);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div1);
    			if_block0.d();
    			if_blocks[current_block_type_index].d();
    			if (if_block2) if_block2.d();
    			mounted = false;
    			dispose();
    		}
    	};
    }

    function instance$m($$self, $$props, $$invalidate) {
    	let currentInstallList;
    	let installListLength;
    	let $installList;
    	component_subscribe($$self, installList, $$value => $$invalidate(6, $installList = $$value));
    	let loading = false;
    	const { Drupal } = window;

    	const handleClick = async () => {
    		$$invalidate(0, loading = true);
    		await processInstallList();
    		$$invalidate(0, loading = false);
    	};

    	function clearSelection() {
    		clearInstallList();
    	}

    	$$self.$$.update = () => {
    		if ($$self.$$.dirty & /*$installList*/ 64) {
    			$$invalidate(5, currentInstallList = $installList || []);
    		}

    		if ($$self.$$.dirty & /*currentInstallList*/ 32) {
    			$$invalidate(1, installListLength = currentInstallList.length);
    		}
    	};

    	return [
    		loading,
    		installListLength,
    		Drupal,
    		handleClick,
    		clearSelection,
    		currentInstallList,
    		$installList
    	];
    }

    class ProcessInstallListButton extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$m, create_fragment$m, safe_not_equal, {});
    	}
    }

    /* src/Search/FilterApplied.svelte generated by Svelte v3.48.0 */

    function create_if_block$e(ctx) {
    	let span;
    	let t_value = window.Drupal.t('Remove @filter', { '@filter': /*label*/ ctx[0] }) + "";
    	let t;

    	return {
    		c() {
    			span = element("span");
    			t = text(t_value);
    			attr(span, "class", "visually-hidden");
    		},
    		m(target, anchor) {
    			insert(target, span, anchor);
    			append(span, t);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*label*/ 1 && t_value !== (t_value = window.Drupal.t('Remove @filter', { '@filter': /*label*/ ctx[0] }) + "")) set_data(t, t_value);
    		},
    		d(detaching) {
    			if (detaching) detach(span);
    		}
    	};
    }

    function create_fragment$l(ctx) {
    	let p;
    	let span;
    	let t0;
    	let t1;
    	let button;
    	let t2;
    	let img;
    	let img_src_value;
    	let mounted;
    	let dispose;
    	let if_block = /*label*/ ctx[0] && create_if_block$e(ctx);

    	return {
    		c() {
    			p = element("p");
    			span = element("span");
    			t0 = text(/*label*/ ctx[0]);
    			t1 = space();
    			button = element("button");
    			if (if_block) if_block.c();
    			t2 = space();
    			img = element("img");
    			attr(span, "class", "filter-applied__label");
    			if (!src_url_equal(img.src, img_src_value = "" + (FULL_MODULE_PATH + "/images/chip-close-icon.svg"))) attr(img, "src", img_src_value);
    			attr(img, "alt", "");
    			attr(button, "type", "button");
    			attr(button, "class", "filter-applied__button-close");
    			attr(p, "class", "filter-applied");
    		},
    		m(target, anchor) {
    			insert(target, p, anchor);
    			append(p, span);
    			append(span, t0);
    			append(p, t1);
    			append(p, button);
    			if (if_block) if_block.m(button, null);
    			append(button, t2);
    			append(button, img);

    			if (!mounted) {
    				dispose = listen(button, "click", function () {
    					if (is_function(/*clickHandler*/ ctx[1])) /*clickHandler*/ ctx[1].apply(this, arguments);
    				});

    				mounted = true;
    			}
    		},
    		p(new_ctx, [dirty]) {
    			ctx = new_ctx;
    			if (dirty & /*label*/ 1) set_data(t0, /*label*/ ctx[0]);

    			if (/*label*/ ctx[0]) {
    				if (if_block) {
    					if_block.p(ctx, dirty);
    				} else {
    					if_block = create_if_block$e(ctx);
    					if_block.c();
    					if_block.m(button, t2);
    				}
    			} else if (if_block) {
    				if_block.d(1);
    				if_block = null;
    			}
    		},
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if (detaching) detach(p);
    			if (if_block) if_block.d();
    			mounted = false;
    			dispose();
    		}
    	};
    }

    function instance$l($$self, $$props, $$invalidate) {
    	let { label } = $$props;
    	let { clickHandler } = $$props;

    	$$self.$$set = $$props => {
    		if ('label' in $$props) $$invalidate(0, label = $$props.label);
    		if ('clickHandler' in $$props) $$invalidate(1, clickHandler = $$props.clickHandler);
    	};

    	return [label, clickHandler];
    }

    class FilterApplied extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$l, create_fragment$l, safe_not_equal, { label: 0, clickHandler: 1 });
    	}
    }

    /* src/Filter/BooleanFilter.svelte generated by Svelte v3.48.0 */

    function create_fragment$k(ctx) {
    	let div;
    	let label;
    	let input;
    	let t0;
    	let span;
    	let t1_value = /*definition*/ ctx[0].name + "";
    	let t1;
    	let mounted;
    	let dispose;

    	return {
    		c() {
    			div = element("div");
    			label = element("label");
    			input = element("input");
    			t0 = space();
    			span = element("span");
    			t1 = text(t1_value);
    			attr(input, "name", /*name*/ ctx[2]);
    			attr(input, "class", "form-checkbox form-boolean form-boolean--type-checkbox");
    			attr(input, "type", "checkbox");
    			attr(span, "class", "checkbox-label");
    			attr(div, "class", "filter-group__filter-options form-item form-type--checkbox");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			append(div, label);
    			append(label, input);
    			input.checked = /*$filters*/ ctx[3][/*name*/ ctx[2]];
    			append(label, t0);
    			append(label, span);
    			append(span, t1);

    			if (!mounted) {
    				dispose = [
    					listen(input, "change", /*input_change_handler*/ ctx[5]),
    					listen(input, "change", function () {
    						if (is_function(/*changeHandler*/ ctx[1])) /*changeHandler*/ ctx[1].apply(this, arguments);
    					})
    				];

    				mounted = true;
    			}
    		},
    		p(new_ctx, [dirty]) {
    			ctx = new_ctx;

    			if (dirty & /*name*/ 4) {
    				attr(input, "name", /*name*/ ctx[2]);
    			}

    			if (dirty & /*$filters, name*/ 12) {
    				input.checked = /*$filters*/ ctx[3][/*name*/ ctx[2]];
    			}

    			if (dirty & /*definition*/ 1 && t1_value !== (t1_value = /*definition*/ ctx[0].name + "")) set_data(t1, t1_value);
    		},
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if (detaching) detach(div);
    			mounted = false;
    			run_all(dispose);
    		}
    	};
    }

    function instance$k($$self, $$props, $$invalidate) {
    	let $filters;
    	let { definition } = $$props;
    	const filters = getContext('filters');
    	component_subscribe($$self, filters, value => $$invalidate(3, $filters = value));
    	let { changeHandler } = $$props;
    	let { name } = $$props;

    	function input_change_handler() {
    		$filters[name] = this.checked;
    		filters.set($filters);
    	}

    	$$self.$$set = $$props => {
    		if ('definition' in $$props) $$invalidate(0, definition = $$props.definition);
    		if ('changeHandler' in $$props) $$invalidate(1, changeHandler = $$props.changeHandler);
    		if ('name' in $$props) $$invalidate(2, name = $$props.name);
    	};

    	return [definition, changeHandler, name, $filters, filters, input_change_handler];
    }

    class BooleanFilter extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$k, create_fragment$k, safe_not_equal, { definition: 0, changeHandler: 1, name: 2 });
    	}
    }

    /* src/Filter/MultipleChoiceFilter.svelte generated by Svelte v3.48.0 */

    function get_each_context$6(ctx, list, i) {
    	const child_ctx = ctx.slice();
    	child_ctx[22] = list[i][0];
    	child_ctx[23] = list[i][1];
    	return child_ctx;
    }

    // (192:6) {:else}
    function create_else_block$5(ctx) {
    	let t_value = window.Drupal.t('Select categories') + "";
    	let t;

    	return {
    		c() {
    			t = text(t_value);
    		},
    		m(target, anchor) {
    			insert(target, t, anchor);
    		},
    		p: noop,
    		d(detaching) {
    			if (detaching) detach(t);
    		}
    	};
    }

    // (186:6) {#if $filters[name].length > 0}
    function create_if_block$d(ctx) {
    	let html_tag;
    	let raw_value = /*Drupal*/ ctx[6].formatPlural(/*$filters*/ ctx[5][/*name*/ ctx[1]].length, '1 category selected', '@count categories selected') + "";
    	let html_anchor;

    	return {
    		c() {
    			html_tag = new HtmlTag(false);
    			html_anchor = empty();
    			html_tag.a = html_anchor;
    		},
    		m(target, anchor) {
    			html_tag.m(raw_value, target, anchor);
    			insert(target, html_anchor, anchor);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*$filters, name*/ 34 && raw_value !== (raw_value = /*Drupal*/ ctx[6].formatPlural(/*$filters*/ ctx[5][/*name*/ ctx[1]].length, '1 category selected', '@count categories selected') + "")) html_tag.p(raw_value);
    		},
    		d(detaching) {
    			if (detaching) detach(html_anchor);
    			if (detaching) html_tag.d();
    		}
    	};
    }

    // (201:6) {#each Object.entries(choices) as [id, label]}
    function create_each_block$6(ctx) {
    	let div;
    	let label;
    	let input;
    	let input_value_value;
    	let t0;
    	let t1_value = /*label*/ ctx[23] + "";
    	let t1;
    	let t2;
    	let mounted;
    	let dispose;

    	return {
    		c() {
    			div = element("div");
    			label = element("label");
    			input = element("input");
    			t0 = space();
    			t1 = text(t1_value);
    			t2 = space();
    			attr(input, "type", "checkbox");
    			attr(input, "class", "pb-filter__checkbox form-checkbox form-boolean form-boolean--type-checkbox");
    			input.__value = input_value_value = /*id*/ ctx[22];
    			input.value = input.__value;
    			/*$$binding_groups*/ ctx[16][0].push(input);
    			attr(div, "class", "pb-filter__checkbox__container");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			append(div, label);
    			append(label, input);
    			input.checked = ~/*$filters*/ ctx[5][/*name*/ ctx[1]].indexOf(input.__value);
    			append(label, t0);
    			append(label, t1);
    			append(div, t2);

    			if (!mounted) {
    				dispose = [
    					listen(input, "change", /*input_change_handler*/ ctx[15]),
    					listen(input, "change", /*onChange*/ ctx[12]),
    					listen(input, "blur", /*onBlur*/ ctx[10]),
    					listen(input, "keydown", /*onKeyDown*/ ctx[11])
    				];

    				mounted = true;
    			}
    		},
    		p(ctx, dirty) {
    			if (dirty & /*choices*/ 1 && input_value_value !== (input_value_value = /*id*/ ctx[22])) {
    				input.__value = input_value_value;
    				input.value = input.__value;
    			}

    			if (dirty & /*$filters, name*/ 34) {
    				input.checked = ~/*$filters*/ ctx[5][/*name*/ ctx[1]].indexOf(input.__value);
    			}

    			if (dirty & /*choices*/ 1 && t1_value !== (t1_value = /*label*/ ctx[23] + "")) set_data(t1, t1_value);
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    			/*$$binding_groups*/ ctx[16][0].splice(/*$$binding_groups*/ ctx[16][0].indexOf(input), 1);
    			mounted = false;
    			run_all(dispose);
    		}
    	};
    }

    function create_fragment$j(ctx) {
    	let div2;
    	let label;
    	let t0_value = window.Drupal.t('Filter by category') + "";
    	let t0;
    	let t1;
    	let div1;
    	let span;
    	let t2;
    	let div0;
    	let div0_class_value;
    	let mounted;
    	let dispose;

    	function select_block_type(ctx, dirty) {
    		if (/*$filters*/ ctx[5][/*name*/ ctx[1]].length > 0) return create_if_block$d;
    		return create_else_block$5;
    	}

    	let current_block_type = select_block_type(ctx);
    	let if_block = current_block_type(ctx);
    	let each_value = Object.entries(/*choices*/ ctx[0]);
    	let each_blocks = [];

    	for (let i = 0; i < each_value.length; i += 1) {
    		each_blocks[i] = create_each_block$6(get_each_context$6(ctx, each_value, i));
    	}

    	return {
    		c() {
    			div2 = element("div");
    			label = element("label");
    			t0 = text(t0_value);
    			t1 = space();
    			div1 = element("div");
    			span = element("span");
    			if_block.c();
    			t2 = space();
    			div0 = element("div");

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].c();
    			}

    			attr(label, "for", `${/*componentId*/ ctx[8]}-dropdown`);
    			attr(label, "class", "form-item__label");
    			attr(span, "class", "pb-filter__multi-dropdown__label");
    			attr(div0, "class", div0_class_value = "pb-filter__multi-dropdown__items pb-filter__multi-dropdown__items--" + (/*filterVisible*/ ctx[2] ? 'visible' : 'hidden'));
    			attr(div1, "id", `${/*componentId*/ ctx[8]}-dropdown`);
    			attr(div1, "role", "group");
    			attr(div1, "tabindex", "0");
    			attr(div1, "class", "pb-filter__multi-dropdown form-element form-element--type-select");
    			attr(div2, "class", "filter-group__filter-options form-item");
    		},
    		m(target, anchor) {
    			insert(target, div2, anchor);
    			append(div2, label);
    			append(label, t0);
    			append(div2, t1);
    			append(div2, div1);
    			append(div1, span);
    			if_block.m(span, null);
    			append(div1, t2);
    			append(div1, div0);

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].m(div0, null);
    			}

    			/*div0_binding*/ ctx[17](div0);
    			/*div1_binding*/ ctx[19](div1);

    			if (!mounted) {
    				dispose = [
    					listen(label, "click", /*click_handler*/ ctx[14]),
    					listen(div1, "click", /*click_handler_1*/ ctx[18]),
    					listen(div1, "blur", /*onBlur*/ ctx[10]),
    					listen(div1, "keydown", /*onKeyDown*/ ctx[11])
    				];

    				mounted = true;
    			}
    		},
    		p(ctx, [dirty]) {
    			if (current_block_type === (current_block_type = select_block_type(ctx)) && if_block) {
    				if_block.p(ctx, dirty);
    			} else {
    				if_block.d(1);
    				if_block = current_block_type(ctx);

    				if (if_block) {
    					if_block.c();
    					if_block.m(span, null);
    				}
    			}

    			if (dirty & /*Object, choices, $filters, name, onChange, onBlur, onKeyDown*/ 7203) {
    				each_value = Object.entries(/*choices*/ ctx[0]);
    				let i;

    				for (i = 0; i < each_value.length; i += 1) {
    					const child_ctx = get_each_context$6(ctx, each_value, i);

    					if (each_blocks[i]) {
    						each_blocks[i].p(child_ctx, dirty);
    					} else {
    						each_blocks[i] = create_each_block$6(child_ctx);
    						each_blocks[i].c();
    						each_blocks[i].m(div0, null);
    					}
    				}

    				for (; i < each_blocks.length; i += 1) {
    					each_blocks[i].d(1);
    				}

    				each_blocks.length = each_value.length;
    			}

    			if (dirty & /*filterVisible*/ 4 && div0_class_value !== (div0_class_value = "pb-filter__multi-dropdown__items pb-filter__multi-dropdown__items--" + (/*filterVisible*/ ctx[2] ? 'visible' : 'hidden'))) {
    				attr(div0, "class", div0_class_value);
    			}
    		},
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if (detaching) detach(div2);
    			if_block.d();
    			destroy_each(each_blocks, detaching);
    			/*div0_binding*/ ctx[17](null);
    			/*div1_binding*/ ctx[19](null);
    			mounted = false;
    			run_all(dispose);
    		}
    	};
    }

    function instance$j($$self, $$props, $$invalidate) {
    	let $filters;
    	const { Drupal } = window;
    	const dispatch = createEventDispatcher();
    	const filters = getContext('filters');
    	component_subscribe($$self, filters, value => $$invalidate(5, $filters = value));
    	let { choices } = $$props;
    	let { name } = $$props;
    	let { filterList } = $$props;
    	let filterVisible = false;
    	let lastFocusedCheckbox = null;

    	// Internal references to the elements that comprise this component.
    	let dropdownElement;

    	let dropdownItemsElement;
    	const componentId = `pb-filter-${Math.random().toString(36).substring(2, 9)}`;

    	function showHideFilter() {
    		$$invalidate(2, filterVisible = !filterVisible);

    		if (filterVisible) {
    			dropdownItemsElement.classList.add('pb-filter__multi-dropdown__items--visible');
    		} else {
    			dropdownItemsElement.classList.remove('pb-filter__multi-dropdown__items--visible');
    		}

    		setTimeout(
    			() => {
    				// Ensure focus stays on the last focused checkbox
    				if (lastFocusedCheckbox) {
    					lastFocusedCheckbox.focus();
    				} else if (dropdownElement) {
    					const firstCheckbox = dropdownElement.querySelector('.pb-filter__checkbox');
    					if (firstCheckbox) firstCheckbox.focus();
    				}
    			},
    			50
    		);
    	}

    	function onBlur(event) {
    		if (event.relatedTarget === null || !dropdownElement.contains(event.relatedTarget)) {
    			$$invalidate(2, filterVisible = false);
    			dropdownItemsElement.classList.remove('pb-filter__multi-dropdown__items--visible');
    		}
    	}

    	function onKeyDown(event) {
    		const isCheckbox = event.target.matches('input');
    		const checkboxes = dropdownItemsElement.querySelectorAll('input');

    		if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
    			event.preventDefault();
    			return;
    		}

    		if (event.key === ' ' && event.target === dropdownElement) {
    			showHideFilter();
    			event.preventDefault();
    			return;
    		}

    		// Alt Up/Down opens/closes category filter drop-down.
    		if (event.altKey && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
    			showHideFilter();
    			event.preventDefault();
    			return;
    		}

    		// Prevent tabbing out when the filter is expanded.
    		if (event.key === 'Tab' && filterVisible) {
    			event.preventDefault();
    			return;
    		}

    		// Down arrow on checkbox moves to next checkbox or wraps around.
    		if (dropdownItemsElement.contains(event.target) && event.key === 'ArrowDown') {
    			const nextElement = event.target.parentElement.parentElement.nextElementSibling;

    			if (nextElement) {
    				nextElement.firstElementChild.focus();
    			} else {
    				// Wrap to the first item
    				checkboxes[0].focus();
    			}

    			event.preventDefault();
    			return;
    		}

    		// Up arrow on checkbox moves to previous checkbox or wraps around.
    		if (isCheckbox && event.key === 'ArrowUp') {
    			const prevElement = event.target.parentElement.parentElement.previousElementSibling;

    			if (prevElement) {
    				prevElement.firstElementChild.focus();
    			} else {
    				// Wrap to the last item
    				checkboxes[checkboxes.length - 1].focus();
    			}

    			event.preventDefault();
    			return;
    		}

    		// Prevent dropdown collapse when moving focus with the arrow key.
    		if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    			event.preventDefault();
    		}

    		// Tab moves off filter.
    		if (event.key === 'Tab') {
    			if (event.shiftKey) {
    				// Shift+tab moves to search box.
    				document.getElementById('pb-text').focus();

    				event.preventDefault();
    				return;
    			}

    			// Tab without shift moves to next filter.
    			const indexOfMyself = filterList.indexOf(name);

    			if (indexOfMyself !== -1 && indexOfMyself + 1 < filterList.length) {
    				const nextElementKey = filterList[indexOfMyself + 1];
    				document.getElementsByName(nextElementKey)[0].focus();
    				event.preventDefault();
    			}

    			return;
    		}

    		// Escape closes filter drop-down.
    		if (isCheckbox && event.key === 'Escape') {
    			$$invalidate(2, filterVisible = false);
    			dropdownElement.focus();
    			dropdownItemsElement.classList.remove('pb-filter__multi-dropdown__items--visible');
    		}
    	}

    	async function onChange(event) {
    		dispatch('FilterChange');
    		$$invalidate(2, filterVisible = true);
    		dropdownItemsElement.classList.add('pb-filter__multi-dropdown__items--visible');

    		if (event.target.matches('input')) {
    			lastFocusedCheckbox = event.target;

    			setTimeout(
    				() => {
    					lastFocusedCheckbox.focus();
    				},
    				50
    			);
    		}
    	}

    	const $$binding_groups = [[]];

    	const click_handler = event => {
    		event.preventDefault();
    		dropdownElement.focus();

    		if (!filterVisible) {
    			showHideFilter();
    		}
    	};

    	function input_change_handler() {
    		$filters[name] = get_binding_group_value($$binding_groups[0], this.__value, this.checked);
    		filters.set($filters);
    	}

    	function div0_binding($$value) {
    		binding_callbacks[$$value ? 'unshift' : 'push'](() => {
    			dropdownItemsElement = $$value;
    			$$invalidate(4, dropdownItemsElement);
    		});
    	}

    	const click_handler_1 = () => {
    		showHideFilter();
    	};

    	function div1_binding($$value) {
    		binding_callbacks[$$value ? 'unshift' : 'push'](() => {
    			dropdownElement = $$value;
    			$$invalidate(3, dropdownElement);
    		});
    	}

    	$$self.$$set = $$props => {
    		if ('choices' in $$props) $$invalidate(0, choices = $$props.choices);
    		if ('name' in $$props) $$invalidate(1, name = $$props.name);
    		if ('filterList' in $$props) $$invalidate(13, filterList = $$props.filterList);
    	};

    	return [
    		choices,
    		name,
    		filterVisible,
    		dropdownElement,
    		dropdownItemsElement,
    		$filters,
    		Drupal,
    		filters,
    		componentId,
    		showHideFilter,
    		onBlur,
    		onKeyDown,
    		onChange,
    		filterList,
    		click_handler,
    		input_change_handler,
    		$$binding_groups,
    		div0_binding,
    		click_handler_1,
    		div1_binding
    	];
    }

    class MultipleChoiceFilter extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$j, create_fragment$j, safe_not_equal, { choices: 0, name: 1, filterList: 13 });
    	}
    }

    /* src/Filter/TextFilter.svelte generated by Svelte v3.48.0 */

    function create_if_block$c(ctx) {
    	let button;
    	let img;
    	let img_src_value;
    	let button_aria_label_value;
    	let mounted;
    	let dispose;

    	return {
    		c() {
    			button = element("button");
    			img = element("img");
    			if (!src_url_equal(img.src, img_src_value = "" + (FULL_MODULE_PATH + "/images/cross" + (DARK_COLOR_SCHEME ? '--dark-color-scheme' : '') + ".svg"))) attr(img, "src", img_src_value);
    			attr(img, "alt", "");
    			attr(button, "class", "search__search-clear");
    			attr(button, "id", "clear-text");
    			attr(button, "type", "button");
    			attr(button, "aria-label", button_aria_label_value = window.Drupal.t('Clear search text'));
    			attr(button, "tabindex", "-1");
    		},
    		m(target, anchor) {
    			insert(target, button, anchor);
    			append(button, img);

    			if (!mounted) {
    				dispose = listen(button, "click", /*clearText*/ ctx[4]);
    				mounted = true;
    			}
    		},
    		p: noop,
    		d(detaching) {
    			if (detaching) detach(button);
    			mounted = false;
    			dispose();
    		}
    	};
    }

    function create_fragment$i(ctx) {
    	let div1;
    	let label;
    	let t1;
    	let div0;
    	let input;
    	let t2;
    	let t3;
    	let button;
    	let img;
    	let img_src_value;
    	let mounted;
    	let dispose;
    	let if_block = /*$filters*/ ctx[1][/*name*/ ctx[0]] && create_if_block$c(ctx);

    	return {
    		c() {
    			div1 = element("div");
    			label = element("label");
    			label.textContent = `${window.Drupal.t('Search')}`;
    			t1 = space();
    			div0 = element("div");
    			input = element("input");
    			t2 = space();
    			if (if_block) if_block.c();
    			t3 = space();
    			button = element("button");
    			img = element("img");
    			attr(label, "for", "pb-text");
    			attr(label, "class", "form-item__label");
    			attr(input, "class", "search__search_term form-text form-element form-element--type-text");
    			attr(input, "type", "search");
    			attr(input, "id", "pb-text");
    			attr(input, "name", /*name*/ ctx[0]);
    			attr(img, "class", "search__search-icon");
    			attr(img, "id", "search-icon");
    			if (!src_url_equal(img.src, img_src_value = "" + (FULL_MODULE_PATH + "/images/search-icon" + (DARK_COLOR_SCHEME ? '--dark-color-scheme' : '') + ".svg"))) attr(img, "src", img_src_value);
    			attr(img, "alt", "");
    			attr(button, "class", "search__search-submit");
    			attr(button, "type", "button");
    			attr(button, "aria-label", window.Drupal.t('Search'));
    			attr(div0, "class", "search__search-bar");
    			attr(div1, "class", "search__bar-container search__form-item js-form-item form-item js-form-type-textfield form-type--textfield");
    			attr(div1, "role", "search");
    		},
    		m(target, anchor) {
    			insert(target, div1, anchor);
    			append(div1, label);
    			append(div1, t1);
    			append(div1, div0);
    			append(div0, input);
    			set_input_value(input, /*$filters*/ ctx[1][/*name*/ ctx[0]]);
    			append(div0, t2);
    			if (if_block) if_block.m(div0, null);
    			append(div0, t3);
    			append(div0, button);
    			append(button, img);

    			if (!mounted) {
    				dispose = [
    					listen(input, "input", /*input_input_handler*/ ctx[6]),
    					listen(input, "keydown", /*keydown_handler*/ ctx[7]),
    					listen(button, "click", /*onClick*/ ctx[3])
    				];

    				mounted = true;
    			}
    		},
    		p(ctx, [dirty]) {
    			if (dirty & /*name*/ 1) {
    				attr(input, "name", /*name*/ ctx[0]);
    			}

    			if (dirty & /*$filters, name*/ 3) {
    				set_input_value(input, /*$filters*/ ctx[1][/*name*/ ctx[0]]);
    			}

    			if (/*$filters*/ ctx[1][/*name*/ ctx[0]]) {
    				if (if_block) {
    					if_block.p(ctx, dirty);
    				} else {
    					if_block = create_if_block$c(ctx);
    					if_block.c();
    					if_block.m(div0, t3);
    				}
    			} else if (if_block) {
    				if_block.d(1);
    				if_block = null;
    			}
    		},
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if (detaching) detach(div1);
    			if (if_block) if_block.d();
    			mounted = false;
    			run_all(dispose);
    		}
    	};
    }

    function instance$i($$self, $$props, $$invalidate) {
    	let $filters;
    	const filters = getContext('filters');
    	component_subscribe($$self, filters, value => $$invalidate(1, $filters = value));
    	const dispatch = createEventDispatcher();
    	let { name } = $$props;
    	let { refresh } = $$props;

    	function onClick() {
    		dispatch('FilterChange', { filters: $filters });
    		refresh();
    	}

    	function clearText() {
    		set_store_value(filters, $filters.search = '', $filters);
    		onClick();
    		document.getElementById('pb-text').focus();
    	}

    	function input_input_handler() {
    		$filters[name] = this.value;
    		filters.set($filters);
    	}

    	const keydown_handler = e => {
    		if (e.key === 'Enter') {
    			e.preventDefault();
    			onClick();
    		}

    		if (e.key === 'Escape') {
    			e.preventDefault();
    			clearText();
    		}
    	};

    	$$self.$$set = $$props => {
    		if ('name' in $$props) $$invalidate(0, name = $$props.name);
    		if ('refresh' in $$props) $$invalidate(5, refresh = $$props.refresh);
    	};

    	return [
    		name,
    		$filters,
    		filters,
    		onClick,
    		clearText,
    		refresh,
    		input_input_handler,
    		keydown_handler
    	];
    }

    class TextFilter extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$i, create_fragment$i, safe_not_equal, { name: 0, refresh: 5 });
    	}
    }

    /* src/Search/SearchSort.svelte generated by Svelte v3.48.0 */

    function get_each_context$5(ctx, list, i) {
    	const child_ctx = ctx.slice();
    	child_ctx[9] = list[i][0];
    	child_ctx[10] = list[i][1];
    	return child_ctx;
    }

    // (31:4) {#each Object.entries(sorts) as [id, text]}
    function create_each_block$5(ctx) {
    	let option;
    	let t0_value = /*text*/ ctx[10] + "";
    	let t0;
    	let t1;
    	let option_value_value;

    	return {
    		c() {
    			option = element("option");
    			t0 = text(t0_value);
    			t1 = space();
    			option.__value = option_value_value = /*id*/ ctx[9];
    			option.value = option.__value;
    		},
    		m(target, anchor) {
    			insert(target, option, anchor);
    			append(option, t0);
    			append(option, t1);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*sorts*/ 1 && t0_value !== (t0_value = /*text*/ ctx[10] + "")) set_data(t0, t0_value);

    			if (dirty & /*sorts*/ 1 && option_value_value !== (option_value_value = /*id*/ ctx[9])) {
    				option.__value = option_value_value;
    				option.value = option.__value;
    			}
    		},
    		d(detaching) {
    			if (detaching) detach(option);
    		}
    	};
    }

    function create_fragment$h(ctx) {
    	let div;
    	let label;
    	let t1;
    	let select;
    	let mounted;
    	let dispose;
    	let each_value = Object.entries(/*sorts*/ ctx[0]);
    	let each_blocks = [];

    	for (let i = 0; i < each_value.length; i += 1) {
    		each_blocks[i] = create_each_block$5(get_each_context$5(ctx, each_value, i));
    	}

    	return {
    		c() {
    			div = element("div");
    			label = element("label");
    			label.textContent = `${window.Drupal.t('Sort by')}`;
    			t1 = space();
    			select = element("select");

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].c();
    			}

    			attr(label, "for", "pb-sort");
    			attr(label, "class", "form-item__label");
    			attr(select, "name", "pb-sort");
    			attr(select, "id", "pb-sort");
    			attr(select, "class", "search__sort-select form-select form-element form-element--type-select");
    			if (/*$sort*/ ctx[1] === void 0) add_render_callback(() => /*select_change_handler*/ ctx[6].call(select));
    			attr(div, "class", "search__sort");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			append(div, label);
    			append(div, t1);
    			append(div, select);

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].m(select, null);
    			}

    			select_option(select, /*$sort*/ ctx[1]);

    			if (!mounted) {
    				dispose = [
    					listen(select, "change", /*select_change_handler*/ ctx[6]),
    					listen(select, "change", /*onSort*/ ctx[3])
    				];

    				mounted = true;
    			}
    		},
    		p(ctx, [dirty]) {
    			if (dirty & /*Object, sorts*/ 1) {
    				each_value = Object.entries(/*sorts*/ ctx[0]);
    				let i;

    				for (i = 0; i < each_value.length; i += 1) {
    					const child_ctx = get_each_context$5(ctx, each_value, i);

    					if (each_blocks[i]) {
    						each_blocks[i].p(child_ctx, dirty);
    					} else {
    						each_blocks[i] = create_each_block$5(child_ctx);
    						each_blocks[i].c();
    						each_blocks[i].m(select, null);
    					}
    				}

    				for (; i < each_blocks.length; i += 1) {
    					each_blocks[i].d(1);
    				}

    				each_blocks.length = each_value.length;
    			}

    			if (dirty & /*$sort, Object, sorts*/ 3) {
    				select_option(select, /*$sort*/ ctx[1]);
    			}
    		},
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if (detaching) detach(div);
    			destroy_each(each_blocks, detaching);
    			mounted = false;
    			run_all(dispose);
    		}
    	};
    }

    function instance$h($$self, $$props, $$invalidate) {
    	let $sort;
    	const sort = getContext('sort');
    	component_subscribe($$self, sort, value => $$invalidate(1, $sort = value));
    	let { sortText } = $$props;
    	let { refresh } = $$props;
    	let { sorts } = $$props;
    	const dispatch = createEventDispatcher();

    	async function onSort() {
    		dispatch('sort', { sort: $sort });
    		$$invalidate(4, sortText = sorts[$sort]);
    		refresh();
    	}

    	function select_change_handler() {
    		$sort = select_value(this);
    		sort.set($sort);
    		$$invalidate(0, sorts);
    	}

    	$$self.$$set = $$props => {
    		if ('sortText' in $$props) $$invalidate(4, sortText = $$props.sortText);
    		if ('refresh' in $$props) $$invalidate(5, refresh = $$props.refresh);
    		if ('sorts' in $$props) $$invalidate(0, sorts = $$props.sorts);
    	};

    	return [sorts, $sort, sort, onSort, sortText, refresh, select_change_handler];
    }

    class SearchSort extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$h, create_fragment$h, safe_not_equal, { sortText: 4, refresh: 5, sorts: 0 });
    	}
    }

    /* src/Search/Search.svelte generated by Svelte v3.48.0 */

    function get_each_context$4(ctx, list, i) {
    	const child_ctx = ctx.slice();
    	child_ctx[21] = list[i];
    	return child_ctx;
    }

    function get_each_context_1$1(ctx, list, i) {
    	const child_ctx = ctx.slice();
    	child_ctx[24] = list[i];
    	return child_ctx;
    }

    function get_each_context_2(ctx, list, i) {
    	const child_ctx = ctx.slice();
    	child_ctx[21] = list[i][0];
    	child_ctx[27] = list[i][1];
    	return child_ctx;
    }

    // (91:4) {#if numberOfFilters > 0}
    function create_if_block_2$5(ctx) {
    	let div;
    	let current;
    	let each_value_2 = Object.entries(/*filterDefinitions*/ ctx[1]);
    	let each_blocks = [];

    	for (let i = 0; i < each_value_2.length; i += 1) {
    		each_blocks[i] = create_each_block_2(get_each_context_2(ctx, each_value_2, i));
    	}

    	const out = i => transition_out(each_blocks[i], 1, 1, () => {
    		each_blocks[i] = null;
    	});

    	return {
    		c() {
    			div = element("div");

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].c();
    			}

    			attr(div, "class", "search__form-filters");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].m(div, null);
    			}

    			current = true;
    		},
    		p(ctx, dirty) {
    			if (dirty & /*Object, filterDefinitions, onFilterChange, refreshLiveRegion*/ 1027) {
    				each_value_2 = Object.entries(/*filterDefinitions*/ ctx[1]);
    				let i;

    				for (i = 0; i < each_value_2.length; i += 1) {
    					const child_ctx = get_each_context_2(ctx, each_value_2, i);

    					if (each_blocks[i]) {
    						each_blocks[i].p(child_ctx, dirty);
    						transition_in(each_blocks[i], 1);
    					} else {
    						each_blocks[i] = create_each_block_2(child_ctx);
    						each_blocks[i].c();
    						transition_in(each_blocks[i], 1);
    						each_blocks[i].m(div, null);
    					}
    				}

    				group_outros();

    				for (i = each_value_2.length; i < each_blocks.length; i += 1) {
    					out(i);
    				}

    				check_outros();
    			}
    		},
    		i(local) {
    			if (current) return;

    			for (let i = 0; i < each_value_2.length; i += 1) {
    				transition_in(each_blocks[i]);
    			}

    			current = true;
    		},
    		o(local) {
    			each_blocks = each_blocks.filter(Boolean);

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				transition_out(each_blocks[i]);
    			}

    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    			destroy_each(each_blocks, detaching);
    		}
    	};
    }

    // (107:44) 
    function create_if_block_5$3(ctx) {
    	let textfilter;
    	let current;

    	textfilter = new TextFilter({
    			props: {
    				name: /*name*/ ctx[21],
    				refresh: /*refreshLiveRegion*/ ctx[0]
    			}
    		});

    	textfilter.$on("FilterChange", /*onFilterChange*/ ctx[10]);

    	return {
    		c() {
    			create_component(textfilter.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(textfilter, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const textfilter_changes = {};
    			if (dirty & /*filterDefinitions*/ 2) textfilter_changes.name = /*name*/ ctx[21];
    			if (dirty & /*refreshLiveRegion*/ 1) textfilter_changes.refresh = /*refreshLiveRegion*/ ctx[0];
    			textfilter.$set(textfilter_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(textfilter.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(textfilter.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(textfilter, detaching);
    		}
    	};
    }

    // (100:55) 
    function create_if_block_4$4(ctx) {
    	let multiplechoicefilter;
    	let current;

    	multiplechoicefilter = new MultipleChoiceFilter({
    			props: {
    				name: /*name*/ ctx[21],
    				filterList: Object.keys(/*filterDefinitions*/ ctx[1]),
    				choices: /*filter*/ ctx[27].choices
    			}
    		});

    	multiplechoicefilter.$on("FilterChange", /*onFilterChange*/ ctx[10]);

    	return {
    		c() {
    			create_component(multiplechoicefilter.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(multiplechoicefilter, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const multiplechoicefilter_changes = {};
    			if (dirty & /*filterDefinitions*/ 2) multiplechoicefilter_changes.name = /*name*/ ctx[21];
    			if (dirty & /*filterDefinitions*/ 2) multiplechoicefilter_changes.filterList = Object.keys(/*filterDefinitions*/ ctx[1]);
    			if (dirty & /*filterDefinitions*/ 2) multiplechoicefilter_changes.choices = /*filter*/ ctx[27].choices;
    			multiplechoicefilter.$set(multiplechoicefilter_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(multiplechoicefilter.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(multiplechoicefilter.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(multiplechoicefilter, detaching);
    		}
    	};
    }

    // (94:10) {#if filter._type === 'boolean'}
    function create_if_block_3$5(ctx) {
    	let booleanfilter;
    	let current;

    	booleanfilter = new BooleanFilter({
    			props: {
    				definition: /*filter*/ ctx[27],
    				name: /*name*/ ctx[21],
    				changeHandler: /*onFilterChange*/ ctx[10]
    			}
    		});

    	return {
    		c() {
    			create_component(booleanfilter.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(booleanfilter, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const booleanfilter_changes = {};
    			if (dirty & /*filterDefinitions*/ 2) booleanfilter_changes.definition = /*filter*/ ctx[27];
    			if (dirty & /*filterDefinitions*/ 2) booleanfilter_changes.name = /*name*/ ctx[21];
    			booleanfilter.$set(booleanfilter_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(booleanfilter.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(booleanfilter.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(booleanfilter, detaching);
    		}
    	};
    }

    // (93:8) {#each Object.entries(filterDefinitions) as [name, filter]}
    function create_each_block_2(ctx) {
    	let current_block_type_index;
    	let if_block;
    	let if_block_anchor;
    	let current;
    	const if_block_creators = [create_if_block_3$5, create_if_block_4$4, create_if_block_5$3];
    	const if_blocks = [];

    	function select_block_type(ctx, dirty) {
    		if (/*filter*/ ctx[27]._type === 'boolean') return 0;
    		if (/*filter*/ ctx[27]._type === 'multiple_choice') return 1;
    		if (/*filter*/ ctx[27]._type === 'text') return 2;
    		return -1;
    	}

    	if (~(current_block_type_index = select_block_type(ctx))) {
    		if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);
    	}

    	return {
    		c() {
    			if (if_block) if_block.c();
    			if_block_anchor = empty();
    		},
    		m(target, anchor) {
    			if (~current_block_type_index) {
    				if_blocks[current_block_type_index].m(target, anchor);
    			}

    			insert(target, if_block_anchor, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			let previous_block_index = current_block_type_index;
    			current_block_type_index = select_block_type(ctx);

    			if (current_block_type_index === previous_block_index) {
    				if (~current_block_type_index) {
    					if_blocks[current_block_type_index].p(ctx, dirty);
    				}
    			} else {
    				if (if_block) {
    					group_outros();

    					transition_out(if_blocks[previous_block_index], 1, 1, () => {
    						if_blocks[previous_block_index] = null;
    					});

    					check_outros();
    				}

    				if (~current_block_type_index) {
    					if_block = if_blocks[current_block_type_index];

    					if (!if_block) {
    						if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);
    						if_block.c();
    					} else {
    						if_block.p(ctx, dirty);
    					}

    					transition_in(if_block, 1);
    					if_block.m(if_block_anchor.parentNode, if_block_anchor);
    				} else {
    					if_block = null;
    				}
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(if_block);
    			current = true;
    		},
    		o(local) {
    			transition_out(if_block);
    			current = false;
    		},
    		d(detaching) {
    			if (~current_block_type_index) {
    				if_blocks[current_block_type_index].d(detaching);
    			}

    			if (detaching) detach(if_block_anchor);
    		}
    	};
    }

    // (120:6) {#if numberOfFilters > 0}
    function create_if_block_1$8(ctx) {
    	let section;
    	let div;
    	let t0;
    	let button0;
    	let t2;
    	let button1;
    	let section_aria_label_value;
    	let current;
    	let mounted;
    	let dispose;
    	let each_value = /*multipleChoiceFilterNames*/ ctx[7];
    	let each_blocks = [];

    	for (let i = 0; i < each_value.length; i += 1) {
    		each_blocks[i] = create_each_block$4(get_each_context$4(ctx, each_value, i));
    	}

    	const out = i => transition_out(each_blocks[i], 1, 1, () => {
    		each_blocks[i] = null;
    	});

    	return {
    		c() {
    			section = element("section");
    			div = element("div");

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].c();
    			}

    			t0 = space();
    			button0 = element("button");
    			button0.textContent = `${window.Drupal.t('Clear filters')}`;
    			t2 = space();
    			button1 = element("button");
    			button1.textContent = `${window.Drupal.t('Recommended filters')}`;
    			attr(button0, "class", "search__filter-button");
    			attr(button0, "type", "button");
    			attr(button1, "class", "search__filter-button");
    			attr(button1, "type", "button");
    			attr(div, "class", "search__results-count");
    			attr(section, "class", "search__filters");
    			attr(section, "aria-label", section_aria_label_value = window.Drupal.t('Search results'));
    		},
    		m(target, anchor) {
    			insert(target, section, anchor);
    			append(section, div);

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].m(div, null);
    			}

    			append(div, t0);
    			append(div, button0);
    			append(div, t2);
    			append(div, button1);
    			current = true;

    			if (!mounted) {
    				dispose = [
    					listen(button0, "click", prevent_default(/*click_handler*/ ctx[14])),
    					listen(button1, "click", prevent_default(/*click_handler_1*/ ctx[15]))
    				];

    				mounted = true;
    			}
    		},
    		p(ctx, dirty) {
    			if (dirty & /*$filters, multipleChoiceFilterNames, filterDefinitions, onFilterChange*/ 1170) {
    				each_value = /*multipleChoiceFilterNames*/ ctx[7];
    				let i;

    				for (i = 0; i < each_value.length; i += 1) {
    					const child_ctx = get_each_context$4(ctx, each_value, i);

    					if (each_blocks[i]) {
    						each_blocks[i].p(child_ctx, dirty);
    						transition_in(each_blocks[i], 1);
    					} else {
    						each_blocks[i] = create_each_block$4(child_ctx);
    						each_blocks[i].c();
    						transition_in(each_blocks[i], 1);
    						each_blocks[i].m(div, t0);
    					}
    				}

    				group_outros();

    				for (i = each_value.length; i < each_blocks.length; i += 1) {
    					out(i);
    				}

    				check_outros();
    			}
    		},
    		i(local) {
    			if (current) return;

    			for (let i = 0; i < each_value.length; i += 1) {
    				transition_in(each_blocks[i]);
    			}

    			current = true;
    		},
    		o(local) {
    			each_blocks = each_blocks.filter(Boolean);

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				transition_out(each_blocks[i]);
    			}

    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(section);
    			destroy_each(each_blocks, detaching);
    			mounted = false;
    			run_all(dispose);
    		}
    	};
    }

    // (127:14) {#each $filters[name] as value}
    function create_each_block_1$1(ctx) {
    	let filterapplied;
    	let current;

    	function func() {
    		return /*func*/ ctx[13](/*name*/ ctx[21], /*value*/ ctx[24]);
    	}

    	filterapplied = new FilterApplied({
    			props: {
    				label: /*filterDefinitions*/ ctx[1][/*name*/ ctx[21]].choices[/*value*/ ctx[24]],
    				clickHandler: func
    			}
    		});

    	return {
    		c() {
    			create_component(filterapplied.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(filterapplied, target, anchor);
    			current = true;
    		},
    		p(new_ctx, dirty) {
    			ctx = new_ctx;
    			const filterapplied_changes = {};
    			if (dirty & /*filterDefinitions, $filters*/ 18) filterapplied_changes.label = /*filterDefinitions*/ ctx[1][/*name*/ ctx[21]].choices[/*value*/ ctx[24]];
    			if (dirty & /*$filters*/ 16) filterapplied_changes.clickHandler = func;
    			filterapplied.$set(filterapplied_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(filterapplied.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(filterapplied.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(filterapplied, detaching);
    		}
    	};
    }

    // (126:12) {#each multipleChoiceFilterNames as name}
    function create_each_block$4(ctx) {
    	let each_1_anchor;
    	let current;
    	let each_value_1 = /*$filters*/ ctx[4][/*name*/ ctx[21]];
    	let each_blocks = [];

    	for (let i = 0; i < each_value_1.length; i += 1) {
    		each_blocks[i] = create_each_block_1$1(get_each_context_1$1(ctx, each_value_1, i));
    	}

    	const out = i => transition_out(each_blocks[i], 1, 1, () => {
    		each_blocks[i] = null;
    	});

    	return {
    		c() {
    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].c();
    			}

    			each_1_anchor = empty();
    		},
    		m(target, anchor) {
    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].m(target, anchor);
    			}

    			insert(target, each_1_anchor, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			if (dirty & /*filterDefinitions, multipleChoiceFilterNames, $filters, onFilterChange*/ 1170) {
    				each_value_1 = /*$filters*/ ctx[4][/*name*/ ctx[21]];
    				let i;

    				for (i = 0; i < each_value_1.length; i += 1) {
    					const child_ctx = get_each_context_1$1(ctx, each_value_1, i);

    					if (each_blocks[i]) {
    						each_blocks[i].p(child_ctx, dirty);
    						transition_in(each_blocks[i], 1);
    					} else {
    						each_blocks[i] = create_each_block_1$1(child_ctx);
    						each_blocks[i].c();
    						transition_in(each_blocks[i], 1);
    						each_blocks[i].m(each_1_anchor.parentNode, each_1_anchor);
    					}
    				}

    				group_outros();

    				for (i = each_value_1.length; i < each_blocks.length; i += 1) {
    					out(i);
    				}

    				check_outros();
    			}
    		},
    		i(local) {
    			if (current) return;

    			for (let i = 0; i < each_value_1.length; i += 1) {
    				transition_in(each_blocks[i]);
    			}

    			current = true;
    		},
    		o(local) {
    			each_blocks = each_blocks.filter(Boolean);

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				transition_out(each_blocks[i]);
    			}

    			current = false;
    		},
    		d(detaching) {
    			destroy_each(each_blocks, detaching);
    			if (detaching) detach(each_1_anchor);
    		}
    	};
    }

    // (156:6) {#if numberOfSorts > 1}
    function create_if_block$b(ctx) {
    	let searchsort;
    	let updating_sortText;
    	let current;

    	function searchsort_sortText_binding(value) {
    		/*searchsort_sortText_binding*/ ctx[16](value);
    	}

    	let searchsort_props = {
    		refresh: /*refreshLiveRegion*/ ctx[0],
    		sorts: /*sorts*/ ctx[2]
    	};

    	if (/*sortText*/ ctx[3] !== void 0) {
    		searchsort_props.sortText = /*sortText*/ ctx[3];
    	}

    	searchsort = new SearchSort({ props: searchsort_props });
    	binding_callbacks.push(() => bind(searchsort, 'sortText', searchsort_sortText_binding));
    	searchsort.$on("sort", /*sort_handler*/ ctx[17]);

    	return {
    		c() {
    			create_component(searchsort.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(searchsort, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const searchsort_changes = {};
    			if (dirty & /*refreshLiveRegion*/ 1) searchsort_changes.refresh = /*refreshLiveRegion*/ ctx[0];
    			if (dirty & /*sorts*/ 4) searchsort_changes.sorts = /*sorts*/ ctx[2];

    			if (!updating_sortText && dirty & /*sortText*/ 8) {
    				updating_sortText = true;
    				searchsort_changes.sortText = /*sortText*/ ctx[3];
    				add_flush_callback(() => updating_sortText = false);
    			}

    			searchsort.$set(searchsort_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(searchsort.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(searchsort.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(searchsort, detaching);
    		}
    	};
    }

    function create_fragment$g(ctx) {
    	let form;
    	let div1;
    	let t0;
    	let div0;
    	let t1;
    	let current;
    	let if_block0 = /*numberOfFilters*/ ctx[8] > 0 && create_if_block_2$5(ctx);
    	let if_block1 = /*numberOfFilters*/ ctx[8] > 0 && create_if_block_1$8(ctx);
    	let if_block2 = /*numberOfSorts*/ ctx[9] > 1 && create_if_block$b(ctx);

    	return {
    		c() {
    			form = element("form");
    			div1 = element("div");
    			if (if_block0) if_block0.c();
    			t0 = space();
    			div0 = element("div");
    			if (if_block1) if_block1.c();
    			t1 = space();
    			if (if_block2) if_block2.c();
    			attr(div0, "class", "search__form-sort js-form-item js-form-type-select form-type--select js-form-item-type form-item--type");
    			attr(div1, "class", "search__form-filters-container");
    			attr(form, "class", "search__form-container");
    		},
    		m(target, anchor) {
    			insert(target, form, anchor);
    			append(form, div1);
    			if (if_block0) if_block0.m(div1, null);
    			append(div1, t0);
    			append(div1, div0);
    			if (if_block1) if_block1.m(div0, null);
    			append(div0, t1);
    			if (if_block2) if_block2.m(div0, null);
    			current = true;
    		},
    		p(ctx, [dirty]) {
    			if (/*numberOfFilters*/ ctx[8] > 0) if_block0.p(ctx, dirty);
    			if (/*numberOfFilters*/ ctx[8] > 0) if_block1.p(ctx, dirty);
    			if (/*numberOfSorts*/ ctx[9] > 1) if_block2.p(ctx, dirty);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(if_block0);
    			transition_in(if_block1);
    			transition_in(if_block2);
    			current = true;
    		},
    		o(local) {
    			transition_out(if_block0);
    			transition_out(if_block1);
    			transition_out(if_block2);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(form);
    			if (if_block0) if_block0.d();
    			if (if_block1) if_block1.d();
    			if (if_block2) if_block2.d();
    		}
    	};
    }

    function instance$g($$self, $$props, $$invalidate) {
    	let $filters;
    	let $sort;
    	const dispatch = createEventDispatcher();
    	const sort = getContext('sort');
    	component_subscribe($$self, sort, value => $$invalidate(18, $sort = value));
    	const filters = getContext('filters');
    	component_subscribe($$self, filters, value => $$invalidate(4, $filters = value));
    	let { refreshLiveRegion } = $$props;
    	let { filterDefinitions } = $$props;
    	let { sorts } = $$props;

    	// List all of the multiple-choice filters, which will be amalgamated into
    	// a lozenge cloud.
    	const multipleChoiceFilterNames = Object.keys(filterDefinitions).filter(name => filterDefinitions[name]._type === 'multiple_choice');

    	const numberOfFilters = Object.keys(filterDefinitions).length;
    	const numberOfSorts = Object.keys(sorts).length;

    	if (!($sort in sorts)) {
    		// eslint-disable-next-line prefer-destructuring
    		set_store_value(sort, $sort = Object.keys(sorts)[0], $sort);
    	}

    	let sortText = sorts[$sort];

    	function onFilterChange(event) {
    		// This function might have been called directly when clearing or resetting
    		// the filters, so we can't rely on the presence of an event.
    		if (event && event.target) {
    			const filterName = event.target.name;

    			if (filterDefinitions[filterName]._type === 'boolean') {
    				set_store_value(filters, $filters[filterName] = event.target.checked, $filters);
    			} else {
    				set_store_value(filters, $filters[filterName] = event.target.value, $filters);
    			}
    		}

    		// Wrapping all the filters and passing up to the components.
    		const detail = { filters: {} };

    		Object.entries($filters).forEach(([key, value]) => {
    			detail.filters[key] = value;
    		});

    		dispatch('FilterChange', detail);
    		refreshLiveRegion();
    	}

    	/**
     * Sets all filters to a falsy value.
     */
    	function clearFilters() {
    		Object.entries(filterDefinitions).forEach(([name, definition]) => {
    			const { _type } = definition;

    			const falsyValuesByType = {
    				boolean: false,
    				multiple_choice: [],
    				text: ''
    			};

    			set_store_value(
    				filters,
    				$filters[name] = _type in falsyValuesByType
    				? falsyValuesByType[_type]
    				: null,
    				$filters
    			);
    		});

    		onFilterChange();
    	}

    	/**
     * Resets the filters to the initial values provided by the source.
     *
     * After calling this, hasUserAppliedFilters() will return false.
     */
    	function resetFilters() {
    		Object.entries(filterDefinitions).forEach(([name, definition]) => {
    			set_store_value(filters, $filters[name] = definition.value, $filters);
    		});

    		onFilterChange();
    	}

    	const func = (name, value) => {
    		$filters[name].splice($filters[name].indexOf(value), 1);
    		filters.set($filters);
    		onFilterChange();
    	};

    	const click_handler = () => clearFilters();
    	const click_handler_1 = () => resetFilters();

    	function searchsort_sortText_binding(value) {
    		sortText = value;
    		$$invalidate(3, sortText);
    	}

    	function sort_handler(event) {
    		bubble.call(this, $$self, event);
    	}

    	$$self.$$set = $$props => {
    		if ('refreshLiveRegion' in $$props) $$invalidate(0, refreshLiveRegion = $$props.refreshLiveRegion);
    		if ('filterDefinitions' in $$props) $$invalidate(1, filterDefinitions = $$props.filterDefinitions);
    		if ('sorts' in $$props) $$invalidate(2, sorts = $$props.sorts);
    	};

    	return [
    		refreshLiveRegion,
    		filterDefinitions,
    		sorts,
    		sortText,
    		$filters,
    		sort,
    		filters,
    		multipleChoiceFilterNames,
    		numberOfFilters,
    		numberOfSorts,
    		onFilterChange,
    		clearFilters,
    		resetFilters,
    		func,
    		click_handler,
    		click_handler_1,
    		searchsort_sortText_binding,
    		sort_handler
    	];
    }

    class Search extends SvelteComponent {
    	constructor(options) {
    		super();

    		init(this, options, instance$g, create_fragment$g, safe_not_equal, {
    			refreshLiveRegion: 0,
    			filterDefinitions: 1,
    			sorts: 2
    		});
    	}
    }

    /* src/ProjectGrid.svelte generated by Svelte v3.48.0 */
    const get_bottom_slot_changes = dirty => ({ rows: dirty & /*visibleRows*/ 4 });
    const get_bottom_slot_context = ctx => ({ rows: /*visibleRows*/ ctx[2] });
    const get_foot_slot_changes = dirty => ({ rows: dirty & /*visibleRows*/ 4 });
    const get_foot_slot_context = ctx => ({ rows: /*visibleRows*/ ctx[2] });
    const get_default_slot_changes$1 = dirty => ({ rows: dirty & /*visibleRows*/ 4 });
    const get_default_slot_context$1 = ctx => ({ rows: /*visibleRows*/ ctx[2] });
    const get_left_slot_changes = dirty => ({ rows: dirty & /*visibleRows*/ 4 });
    const get_left_slot_context = ctx => ({ rows: /*visibleRows*/ ctx[2] });
    const get_head_slot_changes = dirty => ({ rows: dirty & /*visibleRows*/ 4 });
    const get_head_slot_context = ctx => ({ rows: /*visibleRows*/ ctx[2] });

    // (45:4) {:else}
    function create_else_block$4(ctx) {
    	let ul;
    	let ul_class_value;
    	let t;
    	let if_block_anchor;
    	let current;
    	const default_slot_template = /*#slots*/ ctx[12].default;
    	const default_slot = create_slot(default_slot_template, ctx, /*$$scope*/ ctx[11], get_default_slot_context$1);
    	let if_block = PACKAGE_MANAGER && /*processMultipleProjects*/ ctx[4] && create_if_block_1$7();

    	return {
    		c() {
    			ul = element("ul");
    			if (default_slot) default_slot.c();
    			t = space();
    			if (if_block) if_block.c();
    			if_block_anchor = empty();

    			attr(ul, "class", ul_class_value = "pb-projects-" + (/*isDesktop*/ ctx[3]
    			? /*toggleView*/ ctx[0].toLowerCase()
    			: 'list'));

    			attr(ul, "aria-label", window.Drupal.t('Projects'));
    		},
    		m(target, anchor) {
    			insert(target, ul, anchor);

    			if (default_slot) {
    				default_slot.m(ul, null);
    			}

    			insert(target, t, anchor);
    			if (if_block) if_block.m(target, anchor);
    			insert(target, if_block_anchor, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			if (default_slot) {
    				if (default_slot.p && (!current || dirty & /*$$scope, visibleRows*/ 2052)) {
    					update_slot_base(
    						default_slot,
    						default_slot_template,
    						ctx,
    						/*$$scope*/ ctx[11],
    						!current
    						? get_all_dirty_from_scope(/*$$scope*/ ctx[11])
    						: get_slot_changes(default_slot_template, /*$$scope*/ ctx[11], dirty, get_default_slot_changes$1),
    						get_default_slot_context$1
    					);
    				}
    			}

    			if (!current || dirty & /*isDesktop, toggleView*/ 9 && ul_class_value !== (ul_class_value = "pb-projects-" + (/*isDesktop*/ ctx[3]
    			? /*toggleView*/ ctx[0].toLowerCase()
    			: 'list'))) {
    				attr(ul, "class", ul_class_value);
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(default_slot, local);
    			transition_in(if_block);
    			current = true;
    		},
    		o(local) {
    			transition_out(default_slot, local);
    			transition_out(if_block);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(ul);
    			if (default_slot) default_slot.d(detaching);
    			if (detaching) detach(t);
    			if (if_block) if_block.d(detaching);
    			if (detaching) detach(if_block_anchor);
    		}
    	};
    }

    // (43:4) {#if visibleRows.length === 0}
    function create_if_block$a(ctx) {
    	let div;
    	let raw_value = /*labels*/ ctx[1].empty + "";

    	return {
    		c() {
    			div = element("div");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			div.innerHTML = raw_value;
    		},
    		p(ctx, dirty) {
    			if (dirty & /*labels*/ 2 && raw_value !== (raw_value = /*labels*/ ctx[1].empty + "")) div.innerHTML = raw_value;		},
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if (detaching) detach(div);
    		}
    	};
    }

    // (52:6) {#if PACKAGE_MANAGER && processMultipleProjects}
    function create_if_block_1$7(ctx) {
    	let processinstalllistbutton;
    	let current;
    	processinstalllistbutton = new ProcessInstallListButton({});

    	return {
    		c() {
    			create_component(processinstalllistbutton.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(processinstalllistbutton, target, anchor);
    			current = true;
    		},
    		i(local) {
    			if (current) return;
    			transition_in(processinstalllistbutton.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(processinstalllistbutton.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(processinstalllistbutton, detaching);
    		}
    	};
    }

    function create_fragment$f(ctx) {
    	let t0;
    	let div1;
    	let aside;
    	let t1;
    	let div0;
    	let current_block_type_index;
    	let if_block;
    	let t2;
    	let t3;
    	let current;
    	const head_slot_template = /*#slots*/ ctx[12].head;
    	const head_slot = create_slot(head_slot_template, ctx, /*$$scope*/ ctx[11], get_head_slot_context);
    	const left_slot_template = /*#slots*/ ctx[12].left;
    	const left_slot = create_slot(left_slot_template, ctx, /*$$scope*/ ctx[11], get_left_slot_context);
    	const if_block_creators = [create_if_block$a, create_else_block$4];
    	const if_blocks = [];

    	function select_block_type(ctx, dirty) {
    		if (/*visibleRows*/ ctx[2].length === 0) return 0;
    		return 1;
    	}

    	current_block_type_index = select_block_type(ctx);
    	if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);
    	const foot_slot_template = /*#slots*/ ctx[12].foot;
    	const foot_slot = create_slot(foot_slot_template, ctx, /*$$scope*/ ctx[11], get_foot_slot_context);
    	const bottom_slot_template = /*#slots*/ ctx[12].bottom;
    	const bottom_slot = create_slot(bottom_slot_template, ctx, /*$$scope*/ ctx[11], get_bottom_slot_context);

    	return {
    		c() {
    			if (head_slot) head_slot.c();
    			t0 = space();
    			div1 = element("div");
    			aside = element("aside");
    			if (left_slot) left_slot.c();
    			t1 = space();
    			div0 = element("div");
    			if_block.c();
    			t2 = space();
    			if (foot_slot) foot_slot.c();
    			t3 = space();
    			if (bottom_slot) bottom_slot.c();
    			attr(aside, "class", "pb-layout__aside");
    			attr(div0, "class", "pb-layout__main");
    			attr(div1, "class", "pb-layout");
    		},
    		m(target, anchor) {
    			if (head_slot) {
    				head_slot.m(target, anchor);
    			}

    			insert(target, t0, anchor);
    			insert(target, div1, anchor);
    			append(div1, aside);

    			if (left_slot) {
    				left_slot.m(aside, null);
    			}

    			append(div1, t1);
    			append(div1, div0);
    			if_blocks[current_block_type_index].m(div0, null);
    			append(div0, t2);

    			if (foot_slot) {
    				foot_slot.m(div0, null);
    			}

    			insert(target, t3, anchor);

    			if (bottom_slot) {
    				bottom_slot.m(target, anchor);
    			}

    			current = true;
    		},
    		p(ctx, [dirty]) {
    			if (head_slot) {
    				if (head_slot.p && (!current || dirty & /*$$scope, visibleRows*/ 2052)) {
    					update_slot_base(
    						head_slot,
    						head_slot_template,
    						ctx,
    						/*$$scope*/ ctx[11],
    						!current
    						? get_all_dirty_from_scope(/*$$scope*/ ctx[11])
    						: get_slot_changes(head_slot_template, /*$$scope*/ ctx[11], dirty, get_head_slot_changes),
    						get_head_slot_context
    					);
    				}
    			}

    			if (left_slot) {
    				if (left_slot.p && (!current || dirty & /*$$scope, visibleRows*/ 2052)) {
    					update_slot_base(
    						left_slot,
    						left_slot_template,
    						ctx,
    						/*$$scope*/ ctx[11],
    						!current
    						? get_all_dirty_from_scope(/*$$scope*/ ctx[11])
    						: get_slot_changes(left_slot_template, /*$$scope*/ ctx[11], dirty, get_left_slot_changes),
    						get_left_slot_context
    					);
    				}
    			}

    			let previous_block_index = current_block_type_index;
    			current_block_type_index = select_block_type(ctx);

    			if (current_block_type_index === previous_block_index) {
    				if_blocks[current_block_type_index].p(ctx, dirty);
    			} else {
    				group_outros();

    				transition_out(if_blocks[previous_block_index], 1, 1, () => {
    					if_blocks[previous_block_index] = null;
    				});

    				check_outros();
    				if_block = if_blocks[current_block_type_index];

    				if (!if_block) {
    					if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);
    					if_block.c();
    				} else {
    					if_block.p(ctx, dirty);
    				}

    				transition_in(if_block, 1);
    				if_block.m(div0, t2);
    			}

    			if (foot_slot) {
    				if (foot_slot.p && (!current || dirty & /*$$scope, visibleRows*/ 2052)) {
    					update_slot_base(
    						foot_slot,
    						foot_slot_template,
    						ctx,
    						/*$$scope*/ ctx[11],
    						!current
    						? get_all_dirty_from_scope(/*$$scope*/ ctx[11])
    						: get_slot_changes(foot_slot_template, /*$$scope*/ ctx[11], dirty, get_foot_slot_changes),
    						get_foot_slot_context
    					);
    				}
    			}

    			if (bottom_slot) {
    				if (bottom_slot.p && (!current || dirty & /*$$scope, visibleRows*/ 2052)) {
    					update_slot_base(
    						bottom_slot,
    						bottom_slot_template,
    						ctx,
    						/*$$scope*/ ctx[11],
    						!current
    						? get_all_dirty_from_scope(/*$$scope*/ ctx[11])
    						: get_slot_changes(bottom_slot_template, /*$$scope*/ ctx[11], dirty, get_bottom_slot_changes),
    						get_bottom_slot_context
    					);
    				}
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(head_slot, local);
    			transition_in(left_slot, local);
    			transition_in(if_block);
    			transition_in(foot_slot, local);
    			transition_in(bottom_slot, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(head_slot, local);
    			transition_out(left_slot, local);
    			transition_out(if_block);
    			transition_out(foot_slot, local);
    			transition_out(bottom_slot, local);
    			current = false;
    		},
    		d(detaching) {
    			if (head_slot) head_slot.d(detaching);
    			if (detaching) detach(t0);
    			if (detaching) detach(div1);
    			if (left_slot) left_slot.d(detaching);
    			if_blocks[current_block_type_index].d();
    			if (foot_slot) foot_slot.d(detaching);
    			if (detaching) detach(t3);
    			if (bottom_slot) bottom_slot.d(detaching);
    		}
    	};
    }

    function instance$f($$self, $$props, $$invalidate) {
    	let isDesktop;
    	let filteredRows;
    	let visibleRows;
    	let $pageSize;
    	let { $$slots: slots = {}, $$scope } = $$props;
    	const processMultipleProjects = MAX_SELECTIONS === null || MAX_SELECTIONS > 1;
    	const pageSize = getContext('pageSize');
    	component_subscribe($$self, pageSize, value => $$invalidate(10, $pageSize = value));
    	const mediaQueryValues = getContext('mediaQueryValues');
    	let { pageIndex = 0 } = $$props;
    	let { toggleView } = $$props;
    	let { rows } = $$props;

    	let { labels = {
    		empty: window.Drupal.t('No projects found')
    	} } = $$props;

    	let mqMatches;

    	mediaQueryValues.subscribe(mqlMap => {
    		$$invalidate(8, mqMatches = mqlMap.get('(min-width: 1200px)'));
    	});

    	$$self.$$set = $$props => {
    		if ('pageIndex' in $$props) $$invalidate(6, pageIndex = $$props.pageIndex);
    		if ('toggleView' in $$props) $$invalidate(0, toggleView = $$props.toggleView);
    		if ('rows' in $$props) $$invalidate(7, rows = $$props.rows);
    		if ('labels' in $$props) $$invalidate(1, labels = $$props.labels);
    		if ('$$scope' in $$props) $$invalidate(11, $$scope = $$props.$$scope);
    	};

    	$$self.$$.update = () => {
    		if ($$self.$$.dirty & /*mqMatches*/ 256) {
    			$$invalidate(3, isDesktop = mqMatches);
    		}

    		if ($$self.$$.dirty & /*rows*/ 128) {
    			$$invalidate(9, filteredRows = rows);
    		}

    		if ($$self.$$.dirty & /*filteredRows, pageIndex, $pageSize*/ 1600) {
    			$$invalidate(2, visibleRows = filteredRows
    			? filteredRows.slice(pageIndex, pageIndex + $pageSize)
    			: []);
    		}
    	};

    	return [
    		toggleView,
    		labels,
    		visibleRows,
    		isDesktop,
    		processMultipleProjects,
    		pageSize,
    		pageIndex,
    		rows,
    		mqMatches,
    		filteredRows,
    		$pageSize,
    		$$scope,
    		slots
    	];
    }

    class ProjectGrid extends SvelteComponent {
    	constructor(options) {
    		super();

    		init(this, options, instance$f, create_fragment$f, safe_not_equal, {
    			pageIndex: 6,
    			toggleView: 0,
    			rows: 7,
    			labels: 1
    		});
    	}
    }

    /* src/PagerItem.svelte generated by Svelte v3.48.0 */

    function create_fragment$e(ctx) {
    	let li;
    	let a;
    	let t;
    	let a_class_value;
    	let a_aria_label_value;
    	let a_aria_current_value;
    	let li_class_value;
    	let mounted;
    	let dispose;

    	return {
    		c() {
    			li = element("li");
    			a = element("a");
    			t = text(/*label*/ ctx[2]);
    			attr(a, "href", "#pb-sort");
    			attr(a, "class", a_class_value = `pager__link ${/*linkTypes*/ ctx[1].map(func$1).join(' ')}`);
    			attr(a, "aria-label", a_aria_label_value = /*ariaLabel*/ ctx[4] || window.Drupal.t('@location page', { '@location': /*label*/ ctx[2] }));
    			attr(a, "aria-current", a_aria_current_value = /*isCurrent*/ ctx[5] ? 'page' : null);
    			toggle_class(a, "is-active", /*isCurrent*/ ctx[5]);
    			attr(li, "class", li_class_value = `pager__item ${/*itemTypes*/ ctx[0].map(func_1).join(' ')}`);
    			toggle_class(li, "pager__item--active", /*isCurrent*/ ctx[5]);
    		},
    		m(target, anchor) {
    			insert(target, li, anchor);
    			append(li, a);
    			append(a, t);

    			if (!mounted) {
    				dispose = listen(a, "click", /*click_handler*/ ctx[7]);
    				mounted = true;
    			}
    		},
    		p(ctx, [dirty]) {
    			if (dirty & /*label*/ 4) set_data(t, /*label*/ ctx[2]);

    			if (dirty & /*linkTypes*/ 2 && a_class_value !== (a_class_value = `pager__link ${/*linkTypes*/ ctx[1].map(func$1).join(' ')}`)) {
    				attr(a, "class", a_class_value);
    			}

    			if (dirty & /*ariaLabel, label*/ 20 && a_aria_label_value !== (a_aria_label_value = /*ariaLabel*/ ctx[4] || window.Drupal.t('@location page', { '@location': /*label*/ ctx[2] }))) {
    				attr(a, "aria-label", a_aria_label_value);
    			}

    			if (dirty & /*isCurrent*/ 32 && a_aria_current_value !== (a_aria_current_value = /*isCurrent*/ ctx[5] ? 'page' : null)) {
    				attr(a, "aria-current", a_aria_current_value);
    			}

    			if (dirty & /*linkTypes, isCurrent*/ 34) {
    				toggle_class(a, "is-active", /*isCurrent*/ ctx[5]);
    			}

    			if (dirty & /*itemTypes*/ 1 && li_class_value !== (li_class_value = `pager__item ${/*itemTypes*/ ctx[0].map(func_1).join(' ')}`)) {
    				attr(li, "class", li_class_value);
    			}

    			if (dirty & /*itemTypes, isCurrent*/ 33) {
    				toggle_class(li, "pager__item--active", /*isCurrent*/ ctx[5]);
    			}
    		},
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if (detaching) detach(li);
    			mounted = false;
    			dispose();
    		}
    	};
    }

    const func$1 = item => `pager__link--${item}`;
    const func_1 = item => `pager__item--${item}`;

    function instance$e($$self, $$props, $$invalidate) {
    	const dispatch = createEventDispatcher();
    	let { itemTypes = [] } = $$props;
    	let { linkTypes = [] } = $$props;
    	let { label = '' } = $$props;
    	let { toPage = 0 } = $$props;
    	let { ariaLabel = null } = $$props;
    	let { isCurrent = false } = $$props;

    	function onChange(event, selectedPage) {
    		// Preventing the default behavior which causes the
    		// focus to shift on sort-by dropdown element.
    		event.preventDefault();

    		dispatch('pageChange', { page: selectedPage, pageIndex: 0 });
    	}

    	const click_handler = e => onChange(e, toPage);

    	$$self.$$set = $$props => {
    		if ('itemTypes' in $$props) $$invalidate(0, itemTypes = $$props.itemTypes);
    		if ('linkTypes' in $$props) $$invalidate(1, linkTypes = $$props.linkTypes);
    		if ('label' in $$props) $$invalidate(2, label = $$props.label);
    		if ('toPage' in $$props) $$invalidate(3, toPage = $$props.toPage);
    		if ('ariaLabel' in $$props) $$invalidate(4, ariaLabel = $$props.ariaLabel);
    		if ('isCurrent' in $$props) $$invalidate(5, isCurrent = $$props.isCurrent);
    	};

    	return [
    		itemTypes,
    		linkTypes,
    		label,
    		toPage,
    		ariaLabel,
    		isCurrent,
    		onChange,
    		click_handler
    	];
    }

    class PagerItem extends SvelteComponent {
    	constructor(options) {
    		super();

    		init(this, options, instance$e, create_fragment$e, safe_not_equal, {
    			itemTypes: 0,
    			linkTypes: 1,
    			label: 2,
    			toPage: 3,
    			ariaLabel: 4,
    			isCurrent: 5
    		});
    	}
    }

    /* src/Pagination.svelte generated by Svelte v3.48.0 */

    function get_each_context$3(ctx, list, i) {
    	const child_ctx = ctx.slice();
    	child_ctx[18] = list[i];
    	return child_ctx;
    }

    function get_each_context_1(ctx, list, i) {
    	const child_ctx = ctx.slice();
    	child_ctx[21] = list[i];
    	return child_ctx;
    }

    // (30:0) {#if pageCount > 0}
    function create_if_block$9(ctx) {
    	let nav;
    	let t0;
    	let ul;
    	let t1;
    	let t2;
    	let t3;
    	let t4;
    	let nav_aria_label_value;
    	let current;
    	let if_block0 = /*options*/ ctx[2].length > 1 && create_if_block_6$2(ctx);
    	let if_block1 = /*page*/ ctx[1] !== 0 && create_if_block_5$2(ctx);
    	let if_block2 = /*page*/ ctx[1] >= 5 && create_if_block_4$3();
    	let each_value = /*buttons*/ ctx[0];
    	let each_blocks = [];

    	for (let i = 0; i < each_value.length; i += 1) {
    		each_blocks[i] = create_each_block$3(get_each_context$3(ctx, each_value, i));
    	}

    	const out = i => transition_out(each_blocks[i], 1, 1, () => {
    		each_blocks[i] = null;
    	});

    	let if_block3 = /*page*/ ctx[1] + 5 <= /*pageCount*/ ctx[5] && create_if_block_2$4();
    	let if_block4 = /*page*/ ctx[1] !== /*pageCount*/ ctx[5] && create_if_block_1$6(ctx);

    	return {
    		c() {
    			nav = element("nav");
    			if (if_block0) if_block0.c();
    			t0 = space();
    			ul = element("ul");
    			if (if_block1) if_block1.c();
    			t1 = space();
    			if (if_block2) if_block2.c();
    			t2 = space();

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].c();
    			}

    			t3 = space();
    			if (if_block3) if_block3.c();
    			t4 = space();
    			if (if_block4) if_block4.c();
    			attr(ul, "class", "pagination__pager-items pager__items js-pager__items");
    			attr(nav, "class", "pager pagination__pager");
    			attr(nav, "aria-label", nav_aria_label_value = window.Drupal.t('Project Browser Pagination'));
    			attr(nav, "role", "navigation");
    		},
    		m(target, anchor) {
    			insert(target, nav, anchor);
    			if (if_block0) if_block0.m(nav, null);
    			append(nav, t0);
    			append(nav, ul);
    			if (if_block1) if_block1.m(ul, null);
    			append(ul, t1);
    			if (if_block2) if_block2.m(ul, null);
    			append(ul, t2);

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].m(ul, null);
    			}

    			append(ul, t3);
    			if (if_block3) if_block3.m(ul, null);
    			append(ul, t4);
    			if (if_block4) if_block4.m(ul, null);
    			current = true;
    		},
    		p(ctx, dirty) {
    			if (/*options*/ ctx[2].length > 1) {
    				if (if_block0) {
    					if_block0.p(ctx, dirty);
    				} else {
    					if_block0 = create_if_block_6$2(ctx);
    					if_block0.c();
    					if_block0.m(nav, t0);
    				}
    			} else if (if_block0) {
    				if_block0.d(1);
    				if_block0 = null;
    			}

    			if (/*page*/ ctx[1] !== 0) {
    				if (if_block1) {
    					if_block1.p(ctx, dirty);

    					if (dirty & /*page*/ 2) {
    						transition_in(if_block1, 1);
    					}
    				} else {
    					if_block1 = create_if_block_5$2(ctx);
    					if_block1.c();
    					transition_in(if_block1, 1);
    					if_block1.m(ul, t1);
    				}
    			} else if (if_block1) {
    				group_outros();

    				transition_out(if_block1, 1, 1, () => {
    					if_block1 = null;
    				});

    				check_outros();
    			}

    			if (/*page*/ ctx[1] >= 5) {
    				if (if_block2) ; else {
    					if_block2 = create_if_block_4$3();
    					if_block2.c();
    					if_block2.m(ul, t2);
    				}
    			} else if (if_block2) {
    				if_block2.d(1);
    				if_block2 = null;
    			}

    			if (dirty & /*buttons, page, window, pageCount*/ 35) {
    				each_value = /*buttons*/ ctx[0];
    				let i;

    				for (i = 0; i < each_value.length; i += 1) {
    					const child_ctx = get_each_context$3(ctx, each_value, i);

    					if (each_blocks[i]) {
    						each_blocks[i].p(child_ctx, dirty);
    						transition_in(each_blocks[i], 1);
    					} else {
    						each_blocks[i] = create_each_block$3(child_ctx);
    						each_blocks[i].c();
    						transition_in(each_blocks[i], 1);
    						each_blocks[i].m(ul, t3);
    					}
    				}

    				group_outros();

    				for (i = each_value.length; i < each_blocks.length; i += 1) {
    					out(i);
    				}

    				check_outros();
    			}

    			if (/*page*/ ctx[1] + 5 <= /*pageCount*/ ctx[5]) {
    				if (if_block3) ; else {
    					if_block3 = create_if_block_2$4();
    					if_block3.c();
    					if_block3.m(ul, t4);
    				}
    			} else if (if_block3) {
    				if_block3.d(1);
    				if_block3 = null;
    			}

    			if (/*page*/ ctx[1] !== /*pageCount*/ ctx[5]) {
    				if (if_block4) {
    					if_block4.p(ctx, dirty);

    					if (dirty & /*page, pageCount*/ 34) {
    						transition_in(if_block4, 1);
    					}
    				} else {
    					if_block4 = create_if_block_1$6(ctx);
    					if_block4.c();
    					transition_in(if_block4, 1);
    					if_block4.m(ul, null);
    				}
    			} else if (if_block4) {
    				group_outros();

    				transition_out(if_block4, 1, 1, () => {
    					if_block4 = null;
    				});

    				check_outros();
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(if_block1);

    			for (let i = 0; i < each_value.length; i += 1) {
    				transition_in(each_blocks[i]);
    			}

    			transition_in(if_block4);
    			current = true;
    		},
    		o(local) {
    			transition_out(if_block1);
    			each_blocks = each_blocks.filter(Boolean);

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				transition_out(each_blocks[i]);
    			}

    			transition_out(if_block4);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(nav);
    			if (if_block0) if_block0.d();
    			if (if_block1) if_block1.d();
    			if (if_block2) if_block2.d();
    			destroy_each(each_blocks, detaching);
    			if (if_block3) if_block3.d();
    			if (if_block4) if_block4.d();
    		}
    	};
    }

    // (36:4) {#if options.length > 1}
    function create_if_block_6$2(ctx) {
    	let label;
    	let t1;
    	let select;
    	let mounted;
    	let dispose;
    	let each_value_1 = /*options*/ ctx[2];
    	let each_blocks = [];

    	for (let i = 0; i < each_value_1.length; i += 1) {
    		each_blocks[i] = create_each_block_1(get_each_context_1(ctx, each_value_1, i));
    	}

    	return {
    		c() {
    			label = element("label");
    			label.textContent = `${window.Drupal.t('Items per page')}`;
    			t1 = space();
    			select = element("select");

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].c();
    			}

    			attr(label, "for", "num-projects");
    			attr(select, "class", "pagination__num-projects");
    			attr(select, "id", "num-projects");
    			attr(select, "name", "num-projects");
    			if (/*$pageSize*/ ctx[4] === void 0) add_render_callback(() => /*select_change_handler*/ ctx[9].call(select));
    		},
    		m(target, anchor) {
    			insert(target, label, anchor);
    			insert(target, t1, anchor);
    			insert(target, select, anchor);

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].m(select, null);
    			}

    			select_option(select, /*$pageSize*/ ctx[4]);

    			if (!mounted) {
    				dispose = [
    					listen(select, "change", /*select_change_handler*/ ctx[9]),
    					listen(select, "change", /*change_handler*/ ctx[10])
    				];

    				mounted = true;
    			}
    		},
    		p(ctx, dirty) {
    			if (dirty & /*options*/ 4) {
    				each_value_1 = /*options*/ ctx[2];
    				let i;

    				for (i = 0; i < each_value_1.length; i += 1) {
    					const child_ctx = get_each_context_1(ctx, each_value_1, i);

    					if (each_blocks[i]) {
    						each_blocks[i].p(child_ctx, dirty);
    					} else {
    						each_blocks[i] = create_each_block_1(child_ctx);
    						each_blocks[i].c();
    						each_blocks[i].m(select, null);
    					}
    				}

    				for (; i < each_blocks.length; i += 1) {
    					each_blocks[i].d(1);
    				}

    				each_blocks.length = each_value_1.length;
    			}

    			if (dirty & /*$pageSize, options*/ 20) {
    				select_option(select, /*$pageSize*/ ctx[4]);
    			}
    		},
    		d(detaching) {
    			if (detaching) detach(label);
    			if (detaching) detach(t1);
    			if (detaching) detach(select);
    			destroy_each(each_blocks, detaching);
    			mounted = false;
    			run_all(dispose);
    		}
    	};
    }

    // (49:8) {#each options as option}
    function create_each_block_1(ctx) {
    	let option;
    	let t_value = /*option*/ ctx[21] + "";
    	let t;
    	let option_value_value;

    	return {
    		c() {
    			option = element("option");
    			t = text(t_value);
    			option.__value = option_value_value = /*option*/ ctx[21];
    			option.value = option.__value;
    		},
    		m(target, anchor) {
    			insert(target, option, anchor);
    			append(option, t);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*options*/ 4 && t_value !== (t_value = /*option*/ ctx[21] + "")) set_data(t, t_value);

    			if (dirty & /*options*/ 4 && option_value_value !== (option_value_value = /*option*/ ctx[21])) {
    				option.__value = option_value_value;
    				option.value = option.__value;
    			}
    		},
    		d(detaching) {
    			if (detaching) detach(option);
    		}
    	};
    }

    // (56:6) {#if page !== 0}
    function create_if_block_5$2(ctx) {
    	let pageritem0;
    	let t;
    	let pageritem1;
    	let current;

    	pageritem0 = new PagerItem({
    			props: {
    				itemTypes: ['action', 'first'],
    				linkTypes: ['action-link', 'backward'],
    				label: /*labels*/ ctx[3].first,
    				toPage: 0
    			}
    		});

    	pageritem0.$on("pageChange", /*pageChange_handler*/ ctx[11]);

    	pageritem1 = new PagerItem({
    			props: {
    				itemTypes: ['action', 'previous'],
    				linkTypes: ['action-link', 'backward'],
    				label: /*labels*/ ctx[3].previous,
    				toPage: /*page*/ ctx[1] - 1
    			}
    		});

    	pageritem1.$on("pageChange", /*pageChange_handler_1*/ ctx[12]);

    	return {
    		c() {
    			create_component(pageritem0.$$.fragment);
    			t = space();
    			create_component(pageritem1.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(pageritem0, target, anchor);
    			insert(target, t, anchor);
    			mount_component(pageritem1, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const pageritem0_changes = {};
    			if (dirty & /*labels*/ 8) pageritem0_changes.label = /*labels*/ ctx[3].first;
    			pageritem0.$set(pageritem0_changes);
    			const pageritem1_changes = {};
    			if (dirty & /*labels*/ 8) pageritem1_changes.label = /*labels*/ ctx[3].previous;
    			if (dirty & /*page*/ 2) pageritem1_changes.toPage = /*page*/ ctx[1] - 1;
    			pageritem1.$set(pageritem1_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(pageritem0.$$.fragment, local);
    			transition_in(pageritem1.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(pageritem0.$$.fragment, local);
    			transition_out(pageritem1.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(pageritem0, detaching);
    			if (detaching) detach(t);
    			destroy_component(pageritem1, detaching);
    		}
    	};
    }

    // (72:6) {#if page >= 5}
    function create_if_block_4$3(ctx) {
    	let li;

    	return {
    		c() {
    			li = element("li");
    			li.textContent = "…";
    			attr(li, "class", "pager__item pager__item--ellipsis");
    			attr(li, "role", "presentation");
    		},
    		m(target, anchor) {
    			insert(target, li, anchor);
    		},
    		d(detaching) {
    			if (detaching) detach(li);
    		}
    	};
    }

    // (78:8) {#if page + button >= 0 && page + button <= pageCount}
    function create_if_block_3$4(ctx) {
    	let pageritem;
    	let current;

    	pageritem = new PagerItem({
    			props: {
    				itemTypes: ['number'],
    				isCurrent: /*button*/ ctx[18] === 0 ? 'page' : null,
    				label: /*page*/ ctx[1] + /*button*/ ctx[18] + 1,
    				toPage: /*page*/ ctx[1] + /*button*/ ctx[18],
    				ariaLabel: window.Drupal.t('Page @page_number', {
    					'@page_number': /*page*/ ctx[1] + /*button*/ ctx[18] + 1
    				})
    			}
    		});

    	pageritem.$on("pageChange", /*pageChange_handler_2*/ ctx[13]);

    	return {
    		c() {
    			create_component(pageritem.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(pageritem, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const pageritem_changes = {};
    			if (dirty & /*buttons*/ 1) pageritem_changes.isCurrent = /*button*/ ctx[18] === 0 ? 'page' : null;
    			if (dirty & /*page, buttons*/ 3) pageritem_changes.label = /*page*/ ctx[1] + /*button*/ ctx[18] + 1;
    			if (dirty & /*page, buttons*/ 3) pageritem_changes.toPage = /*page*/ ctx[1] + /*button*/ ctx[18];

    			if (dirty & /*page, buttons*/ 3) pageritem_changes.ariaLabel = window.Drupal.t('Page @page_number', {
    				'@page_number': /*page*/ ctx[1] + /*button*/ ctx[18] + 1
    			});

    			pageritem.$set(pageritem_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(pageritem.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(pageritem.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(pageritem, detaching);
    		}
    	};
    }

    // (77:6) {#each buttons as button}
    function create_each_block$3(ctx) {
    	let if_block_anchor;
    	let current;
    	let if_block = /*page*/ ctx[1] + /*button*/ ctx[18] >= 0 && /*page*/ ctx[1] + /*button*/ ctx[18] <= /*pageCount*/ ctx[5] && create_if_block_3$4(ctx);

    	return {
    		c() {
    			if (if_block) if_block.c();
    			if_block_anchor = empty();
    		},
    		m(target, anchor) {
    			if (if_block) if_block.m(target, anchor);
    			insert(target, if_block_anchor, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			if (/*page*/ ctx[1] + /*button*/ ctx[18] >= 0 && /*page*/ ctx[1] + /*button*/ ctx[18] <= /*pageCount*/ ctx[5]) {
    				if (if_block) {
    					if_block.p(ctx, dirty);

    					if (dirty & /*page, buttons, pageCount*/ 35) {
    						transition_in(if_block, 1);
    					}
    				} else {
    					if_block = create_if_block_3$4(ctx);
    					if_block.c();
    					transition_in(if_block, 1);
    					if_block.m(if_block_anchor.parentNode, if_block_anchor);
    				}
    			} else if (if_block) {
    				group_outros();

    				transition_out(if_block, 1, 1, () => {
    					if_block = null;
    				});

    				check_outros();
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(if_block);
    			current = true;
    		},
    		o(local) {
    			transition_out(if_block);
    			current = false;
    		},
    		d(detaching) {
    			if (if_block) if_block.d(detaching);
    			if (detaching) detach(if_block_anchor);
    		}
    	};
    }

    // (91:6) {#if page + 5 <= pageCount}
    function create_if_block_2$4(ctx) {
    	let li;

    	return {
    		c() {
    			li = element("li");
    			li.textContent = "…";
    			attr(li, "class", "pager__item pager__item--ellipsis");
    			attr(li, "role", "presentation");
    		},
    		m(target, anchor) {
    			insert(target, li, anchor);
    		},
    		d(detaching) {
    			if (detaching) detach(li);
    		}
    	};
    }

    // (96:6) {#if page !== pageCount}
    function create_if_block_1$6(ctx) {
    	let pageritem0;
    	let t;
    	let pageritem1;
    	let current;

    	pageritem0 = new PagerItem({
    			props: {
    				itemTypes: ['action', 'next'],
    				linkTypes: ['action-link', 'forward'],
    				label: /*labels*/ ctx[3].next,
    				toPage: /*page*/ ctx[1] + 1
    			}
    		});

    	pageritem0.$on("pageChange", /*pageChange_handler_3*/ ctx[14]);

    	pageritem1 = new PagerItem({
    			props: {
    				itemTypes: ['action', 'last'],
    				linkTypes: ['action-link', 'forward'],
    				label: /*labels*/ ctx[3].last,
    				toPage: /*pageCount*/ ctx[5]
    			}
    		});

    	pageritem1.$on("pageChange", /*pageChange_handler_4*/ ctx[15]);

    	return {
    		c() {
    			create_component(pageritem0.$$.fragment);
    			t = space();
    			create_component(pageritem1.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(pageritem0, target, anchor);
    			insert(target, t, anchor);
    			mount_component(pageritem1, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const pageritem0_changes = {};
    			if (dirty & /*labels*/ 8) pageritem0_changes.label = /*labels*/ ctx[3].next;
    			if (dirty & /*page*/ 2) pageritem0_changes.toPage = /*page*/ ctx[1] + 1;
    			pageritem0.$set(pageritem0_changes);
    			const pageritem1_changes = {};
    			if (dirty & /*labels*/ 8) pageritem1_changes.label = /*labels*/ ctx[3].last;
    			if (dirty & /*pageCount*/ 32) pageritem1_changes.toPage = /*pageCount*/ ctx[5];
    			pageritem1.$set(pageritem1_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(pageritem0.$$.fragment, local);
    			transition_in(pageritem1.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(pageritem0.$$.fragment, local);
    			transition_out(pageritem1.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(pageritem0, detaching);
    			if (detaching) detach(t);
    			destroy_component(pageritem1, detaching);
    		}
    	};
    }

    function create_fragment$d(ctx) {
    	let if_block_anchor;
    	let current;
    	let if_block = /*pageCount*/ ctx[5] > 0 && create_if_block$9(ctx);

    	return {
    		c() {
    			if (if_block) if_block.c();
    			if_block_anchor = empty();
    		},
    		m(target, anchor) {
    			if (if_block) if_block.m(target, anchor);
    			insert(target, if_block_anchor, anchor);
    			current = true;
    		},
    		p(ctx, [dirty]) {
    			if (/*pageCount*/ ctx[5] > 0) {
    				if (if_block) {
    					if_block.p(ctx, dirty);

    					if (dirty & /*pageCount*/ 32) {
    						transition_in(if_block, 1);
    					}
    				} else {
    					if_block = create_if_block$9(ctx);
    					if_block.c();
    					transition_in(if_block, 1);
    					if_block.m(if_block_anchor.parentNode, if_block_anchor);
    				}
    			} else if (if_block) {
    				group_outros();

    				transition_out(if_block, 1, 1, () => {
    					if_block = null;
    				});

    				check_outros();
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(if_block);
    			current = true;
    		},
    		o(local) {
    			transition_out(if_block);
    			current = false;
    		},
    		d(detaching) {
    			if (if_block) if_block.d(detaching);
    			if (detaching) detach(if_block_anchor);
    		}
    	};
    }

    function instance$d($$self, $$props, $$invalidate) {
    	let pageCount;
    	let $pageSize;
    	const dispatch = createEventDispatcher();

    	function pageSizeChange() {
    		dispatch('pageSizeChange');
    	}
    	const pageSize = getContext('pageSize');
    	component_subscribe($$self, pageSize, value => $$invalidate(4, $pageSize = value));
    	let { buttons = [-4, -3, -2, -1, 0, 1, 2, 3, 4] } = $$props;
    	let { count } = $$props;
    	let { page = 0 } = $$props;
    	let { options } = $$props;

    	let { labels = {
    		first: window.Drupal.t('First'),
    		last: window.Drupal.t('Last'),
    		next: window.Drupal.t('Next'),
    		previous: window.Drupal.t('Previous')
    	} } = $$props;

    	function select_change_handler() {
    		$pageSize = select_value(this);
    		pageSize.set($pageSize);
    		$$invalidate(2, options);
    	}

    	const change_handler = () => {
    		pageSizeChange();
    	};

    	function pageChange_handler(event) {
    		bubble.call(this, $$self, event);
    	}

    	function pageChange_handler_1(event) {
    		bubble.call(this, $$self, event);
    	}

    	function pageChange_handler_2(event) {
    		bubble.call(this, $$self, event);
    	}

    	function pageChange_handler_3(event) {
    		bubble.call(this, $$self, event);
    	}

    	function pageChange_handler_4(event) {
    		bubble.call(this, $$self, event);
    	}

    	$$self.$$set = $$props => {
    		if ('buttons' in $$props) $$invalidate(0, buttons = $$props.buttons);
    		if ('count' in $$props) $$invalidate(8, count = $$props.count);
    		if ('page' in $$props) $$invalidate(1, page = $$props.page);
    		if ('options' in $$props) $$invalidate(2, options = $$props.options);
    		if ('labels' in $$props) $$invalidate(3, labels = $$props.labels);
    	};

    	$$self.$$.update = () => {
    		if ($$self.$$.dirty & /*count, $pageSize*/ 272) {
    			$$invalidate(5, pageCount = Math.ceil(count / $pageSize) - 1);
    		}
    	};

    	return [
    		buttons,
    		page,
    		options,
    		labels,
    		$pageSize,
    		pageCount,
    		pageSizeChange,
    		pageSize,
    		count,
    		select_change_handler,
    		change_handler,
    		pageChange_handler,
    		pageChange_handler_1,
    		pageChange_handler_2,
    		pageChange_handler_3,
    		pageChange_handler_4
    	];
    }

    class Pagination extends SvelteComponent {
    	constructor(options) {
    		super();

    		init(this, options, instance$d, create_fragment$d, safe_not_equal, {
    			buttons: 0,
    			count: 8,
    			page: 1,
    			options: 2,
    			labels: 3
    		});
    	}
    }

    /* src/Project/ProjectButtonBase.svelte generated by Svelte v3.48.0 */

    function create_fragment$c(ctx) {
    	let button;
    	let current;
    	let mounted;
    	let dispose;
    	const default_slot_template = /*#slots*/ ctx[3].default;
    	const default_slot = create_slot(default_slot_template, ctx, /*$$scope*/ ctx[2], null);
    	let button_levels = [{ class: "pb__action_button select_button" }, /*$$restProps*/ ctx[1]];
    	let button_data = {};

    	for (let i = 0; i < button_levels.length; i += 1) {
    		button_data = assign(button_data, button_levels[i]);
    	}

    	return {
    		c() {
    			button = element("button");
    			if (default_slot) default_slot.c();
    			set_attributes(button, button_data);
    		},
    		m(target, anchor) {
    			insert(target, button, anchor);

    			if (default_slot) {
    				default_slot.m(button, null);
    			}

    			if (button.autofocus) button.focus();
    			current = true;

    			if (!mounted) {
    				dispose = listen(button, "click", function () {
    					if (is_function(/*click*/ ctx[0])) /*click*/ ctx[0].apply(this, arguments);
    				});

    				mounted = true;
    			}
    		},
    		p(new_ctx, [dirty]) {
    			ctx = new_ctx;

    			if (default_slot) {
    				if (default_slot.p && (!current || dirty & /*$$scope*/ 4)) {
    					update_slot_base(
    						default_slot,
    						default_slot_template,
    						ctx,
    						/*$$scope*/ ctx[2],
    						!current
    						? get_all_dirty_from_scope(/*$$scope*/ ctx[2])
    						: get_slot_changes(default_slot_template, /*$$scope*/ ctx[2], dirty, null),
    						null
    					);
    				}
    			}

    			set_attributes(button, button_data = get_spread_update(button_levels, [
    				{ class: "pb__action_button select_button" },
    				dirty & /*$$restProps*/ 2 && /*$$restProps*/ ctx[1]
    			]));
    		},
    		i(local) {
    			if (current) return;
    			transition_in(default_slot, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(default_slot, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(button);
    			if (default_slot) default_slot.d(detaching);
    			mounted = false;
    			dispose();
    		}
    	};
    }

    function instance$c($$self, $$props, $$invalidate) {
    	const omit_props_names = ["click"];
    	let $$restProps = compute_rest_props($$props, omit_props_names);
    	let { $$slots: slots = {}, $$scope } = $$props;

    	let { click = () => {
    		
    	} } = $$props;

    	$$self.$$set = $$new_props => {
    		$$props = assign(assign({}, $$props), exclude_internal_props($$new_props));
    		$$invalidate(1, $$restProps = compute_rest_props($$props, omit_props_names));
    		if ('click' in $$new_props) $$invalidate(0, click = $$new_props.click);
    		if ('$$scope' in $$new_props) $$invalidate(2, $$scope = $$new_props.$$scope);
    	};

    	return [click, $$restProps, $$scope, slots];
    }

    class ProjectButtonBase extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$c, create_fragment$c, safe_not_equal, { click: 0 });
    	}
    }

    /* src/Project/ProjectStatusIndicator.svelte generated by Svelte v3.48.0 */

    function create_fragment$b(ctx) {
    	let button;
    	let t0;
    	let span0;
    	let t1_value = window.Drupal.t('@module is', { '@module': `${/*project*/ ctx[0].title}` }) + "";
    	let t1;
    	let t2;
    	let span1;
    	let t3;
    	let current;
    	const default_slot_template = /*#slots*/ ctx[3].default;
    	const default_slot = create_slot(default_slot_template, ctx, /*$$scope*/ ctx[2], null);

    	return {
    		c() {
    			button = element("button");
    			if (default_slot) default_slot.c();
    			t0 = space();
    			span0 = element("span");
    			t1 = text(t1_value);
    			t2 = space();
    			span1 = element("span");
    			t3 = text(/*statusText*/ ctx[1]);
    			attr(span0, "class", "visually-hidden");
    			attr(span1, "class", "project_status-indicator__label");
    			attr(button, "class", "project_status-indicator");
    			attr(button, "aria-disabled", "true");
    		},
    		m(target, anchor) {
    			insert(target, button, anchor);

    			if (default_slot) {
    				default_slot.m(button, null);
    			}

    			append(button, t0);
    			append(button, span0);
    			append(span0, t1);
    			append(button, t2);
    			append(button, span1);
    			append(span1, t3);
    			current = true;
    		},
    		p(ctx, [dirty]) {
    			if (default_slot) {
    				if (default_slot.p && (!current || dirty & /*$$scope*/ 4)) {
    					update_slot_base(
    						default_slot,
    						default_slot_template,
    						ctx,
    						/*$$scope*/ ctx[2],
    						!current
    						? get_all_dirty_from_scope(/*$$scope*/ ctx[2])
    						: get_slot_changes(default_slot_template, /*$$scope*/ ctx[2], dirty, null),
    						null
    					);
    				}
    			}

    			if ((!current || dirty & /*project*/ 1) && t1_value !== (t1_value = window.Drupal.t('@module is', { '@module': `${/*project*/ ctx[0].title}` }) + "")) set_data(t1, t1_value);
    			if (!current || dirty & /*statusText*/ 2) set_data(t3, /*statusText*/ ctx[1]);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(default_slot, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(default_slot, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(button);
    			if (default_slot) default_slot.d(detaching);
    		}
    	};
    }

    function instance$b($$self, $$props, $$invalidate) {
    	let { $$slots: slots = {}, $$scope } = $$props;
    	let { project } = $$props;
    	let { statusText } = $$props;

    	$$self.$$set = $$props => {
    		if ('project' in $$props) $$invalidate(0, project = $$props.project);
    		if ('statusText' in $$props) $$invalidate(1, statusText = $$props.statusText);
    		if ('$$scope' in $$props) $$invalidate(2, $$scope = $$props.$$scope);
    	};

    	return [project, statusText, $$scope, slots];
    }

    class ProjectStatusIndicator extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$b, create_fragment$b, safe_not_equal, { project: 0, statusText: 1 });
    	}
    }

    /* src/Project/DropButton.svelte generated by Svelte v3.48.0 */

    function get_each_context$2(ctx, list, i) {
    	const child_ctx = ctx.slice();
    	child_ctx[7] = list[i];
    	return child_ctx;
    }

    // (75:6) {#if tasks.length > 1}
    function create_if_block$8(ctx) {
    	let li;
    	let button;
    	let t1;
    	let each_1_anchor;
    	let mounted;
    	let dispose;
    	let each_value = /*tasks*/ ctx[0].slice(1);
    	let each_blocks = [];

    	for (let i = 0; i < each_value.length; i += 1) {
    		each_blocks[i] = create_each_block$2(get_each_context$2(ctx, each_value, i));
    	}

    	return {
    		c() {
    			li = element("li");
    			button = element("button");
    			button.innerHTML = `<span class="visually-hidden">List additional actions</span>`;
    			t1 = space();

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].c();
    			}

    			each_1_anchor = empty();
    			attr(button, "type", "button");
    			attr(button, "class", "dropbutton__toggle");
    			attr(li, "class", "dropbutton-toggle");
    		},
    		m(target, anchor) {
    			insert(target, li, anchor);
    			append(li, button);
    			insert(target, t1, anchor);

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].m(target, anchor);
    			}

    			insert(target, each_1_anchor, anchor);

    			if (!mounted) {
    				dispose = listen(button, "click", /*toggleDropdown*/ ctx[2]);
    				mounted = true;
    			}
    		},
    		p(ctx, dirty) {
    			if (dirty & /*tasks*/ 1) {
    				each_value = /*tasks*/ ctx[0].slice(1);
    				let i;

    				for (i = 0; i < each_value.length; i += 1) {
    					const child_ctx = get_each_context$2(ctx, each_value, i);

    					if (each_blocks[i]) {
    						each_blocks[i].p(child_ctx, dirty);
    					} else {
    						each_blocks[i] = create_each_block$2(child_ctx);
    						each_blocks[i].c();
    						each_blocks[i].m(each_1_anchor.parentNode, each_1_anchor);
    					}
    				}

    				for (; i < each_blocks.length; i += 1) {
    					each_blocks[i].d(1);
    				}

    				each_blocks.length = each_value.length;
    			}
    		},
    		d(detaching) {
    			if (detaching) detach(li);
    			if (detaching) detach(t1);
    			destroy_each(each_blocks, detaching);
    			if (detaching) detach(each_1_anchor);
    			mounted = false;
    			dispose();
    		}
    	};
    }

    // (86:8) {#each tasks.slice(1) as task}
    function create_each_block$2(ctx) {
    	let li;
    	let a;
    	let t0_value = /*task*/ ctx[7].text + "";
    	let t0;
    	let a_href_value;
    	let t1;

    	return {
    		c() {
    			li = element("li");
    			a = element("a");
    			t0 = text(t0_value);
    			t1 = space();
    			attr(a, "href", a_href_value = /*task*/ ctx[7].url);
    			toggle_class(a, "use-ajax", /*task*/ ctx[7].ajax);
    			attr(li, "class", "dropbutton__item dropbutton-action secondary-action");
    		},
    		m(target, anchor) {
    			insert(target, li, anchor);
    			append(li, a);
    			append(a, t0);
    			append(li, t1);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*tasks*/ 1 && t0_value !== (t0_value = /*task*/ ctx[7].text + "")) set_data(t0, t0_value);

    			if (dirty & /*tasks*/ 1 && a_href_value !== (a_href_value = /*task*/ ctx[7].url)) {
    				attr(a, "href", a_href_value);
    			}

    			if (dirty & /*tasks*/ 1) {
    				toggle_class(a, "use-ajax", /*task*/ ctx[7].ajax);
    			}
    		},
    		d(detaching) {
    			if (detaching) detach(li);
    		}
    	};
    }

    function create_fragment$a(ctx) {
    	let div1;
    	let div0;
    	let ul;
    	let li;
    	let a;
    	let t0_value = /*tasks*/ ctx[0][0].text + "";
    	let t0;
    	let a_href_value;
    	let t1;
    	let mounted;
    	let dispose;
    	let if_block = /*tasks*/ ctx[0].length > 1 && create_if_block$8(ctx);

    	return {
    		c() {
    			div1 = element("div");
    			div0 = element("div");
    			ul = element("ul");
    			li = element("li");
    			a = element("a");
    			t0 = text(t0_value);
    			t1 = space();
    			if (if_block) if_block.c();
    			attr(a, "href", a_href_value = /*tasks*/ ctx[0][0].url);
    			attr(a, "class", "pb__action_button");
    			toggle_class(a, "use-ajax", /*tasks*/ ctx[0][0].ajax);
    			attr(li, "class", "dropbutton__item dropbutton-action");
    			attr(ul, "class", "dropbutton dropbutton--extrasmall dropbutton--multiple");
    			attr(div0, "class", "dropbutton-widget");
    			attr(div1, "class", "dropbutton-wrapper dropbutton-multiple");
    			attr(div1, "data-once", "dropbutton");
    		},
    		m(target, anchor) {
    			insert(target, div1, anchor);
    			append(div1, div0);
    			append(div0, ul);
    			append(ul, li);
    			append(li, a);
    			append(a, t0);
    			append(ul, t1);
    			if (if_block) if_block.m(ul, null);
    			/*div1_binding*/ ctx[3](div1);

    			if (!mounted) {
    				dispose = listen(a, "click", click_handler);
    				mounted = true;
    			}
    		},
    		p(ctx, [dirty]) {
    			if (dirty & /*tasks*/ 1 && t0_value !== (t0_value = /*tasks*/ ctx[0][0].text + "")) set_data(t0, t0_value);

    			if (dirty & /*tasks*/ 1 && a_href_value !== (a_href_value = /*tasks*/ ctx[0][0].url)) {
    				attr(a, "href", a_href_value);
    			}

    			if (dirty & /*tasks*/ 1) {
    				toggle_class(a, "use-ajax", /*tasks*/ ctx[0][0].ajax);
    			}

    			if (/*tasks*/ ctx[0].length > 1) {
    				if (if_block) {
    					if_block.p(ctx, dirty);
    				} else {
    					if_block = create_if_block$8(ctx);
    					if_block.c();
    					if_block.m(ul, null);
    				}
    			} else if (if_block) {
    				if_block.d(1);
    				if_block = null;
    			}
    		},
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if (detaching) detach(div1);
    			if (if_block) if_block.d();
    			/*div1_binding*/ ctx[3](null);
    			mounted = false;
    			dispose();
    		}
    	};
    }

    const click_handler = () => {
    	
    };

    function instance$a($$self, $$props, $$invalidate) {
    	let { tasks = [] } = $$props;
    	const { Drupal } = window;

    	// Toggle the dropdown visibility for the clicked drop button
    	const toggleDropdown = event => {
    		const wrapper = event.currentTarget.closest('.dropbutton-wrapper');
    		const isOpen = wrapper.classList.contains('open');

    		// Close all open dropdowns first
    		document.querySelectorAll('.dropbutton-wrapper.open').forEach(el => {
    			el.classList.remove('open');
    		});

    		if (!isOpen) {
    			wrapper.classList.add('open');
    		}
    	};

    	// Handle keydown for closing the dropdown with Escape
    	const handleKeyDown = event => {
    		// Query the DOM for getting the only opened dropbutton.
    		const openDropdown = document.querySelector('.dropbutton-wrapper.open');

    		if (!openDropdown) return;

    		// If there are no items in the dropdown, exit early
    		if (!openDropdown.querySelectorAll('.secondary-action a').length) return;

    		const toggleButton = openDropdown.querySelector('.dropbutton__toggle');

    		if (event.key === 'Escape') {
    			openDropdown.classList.remove('open');
    			toggleButton.focus();
    		}
    	};

    	// Close the dropdown if clicked outside
    	const closeDropdownOnOutsideClick = event => {
    		document.querySelectorAll('.dropbutton-wrapper.open').forEach(wrapper => {
    			if (!wrapper.contains(event.target)) {
    				wrapper.classList.remove('open');
    			}
    		});
    	};

    	document.addEventListener('click', closeDropdownOnOutsideClick);
    	document.addEventListener('keydown', handleKeyDown);
    	let thisElement;

    	afterUpdate(() => {
    		Drupal.attachBehaviors(thisElement);
    	});

    	function div1_binding($$value) {
    		binding_callbacks[$$value ? 'unshift' : 'push'](() => {
    			thisElement = $$value;
    			$$invalidate(1, thisElement);
    		});
    	}

    	$$self.$$set = $$props => {
    		if ('tasks' in $$props) $$invalidate(0, tasks = $$props.tasks);
    	};

    	return [tasks, thisElement, toggleDropdown, div1_binding];
    }

    class DropButton extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$a, create_fragment$a, safe_not_equal, { tasks: 0 });
    	}
    }

    /* src/Project/ProjectIcon.svelte generated by Svelte v3.48.0 */

    function create_else_block$3(ctx) {
    	let button;
    	let img;
    	let img_src_value;
    	let img_class_value;
    	let button_title_value;

    	return {
    		c() {
    			button = element("button");
    			img = element("img");
    			if (!src_url_equal(img.src, img_src_value = "" + (FULL_MODULE_PATH + "/images/" + /*typeToImg*/ ctx[4][/*type*/ ctx[0]].path + (DARK_COLOR_SCHEME ? '--dark-color-scheme' : '') + ".svg"))) attr(img, "src", img_src_value);
    			attr(img, "class", img_class_value = `pb-icon pb-icon--${/*variant*/ ctx[1]} pb-icon--${/*type*/ ctx[0]} ${/*classes*/ ctx[2]}`);
    			attr(img, "alt", /*alt*/ ctx[5]);
    			attr(button, "class", "pb-project__status-icon-btn");
    			attr(button, "title", button_title_value = /*typeToImg*/ ctx[4][/*type*/ ctx[0]].title);
    		},
    		m(target, anchor) {
    			insert(target, button, anchor);
    			append(button, img);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*type*/ 1 && !src_url_equal(img.src, img_src_value = "" + (FULL_MODULE_PATH + "/images/" + /*typeToImg*/ ctx[4][/*type*/ ctx[0]].path + (DARK_COLOR_SCHEME ? '--dark-color-scheme' : '') + ".svg"))) {
    				attr(img, "src", img_src_value);
    			}

    			if (dirty & /*variant, type, classes*/ 7 && img_class_value !== (img_class_value = `pb-icon pb-icon--${/*variant*/ ctx[1]} pb-icon--${/*type*/ ctx[0]} ${/*classes*/ ctx[2]}`)) {
    				attr(img, "class", img_class_value);
    			}

    			if (dirty & /*type*/ 1 && button_title_value !== (button_title_value = /*typeToImg*/ ctx[4][/*type*/ ctx[0]].title)) {
    				attr(button, "title", button_title_value);
    			}
    		},
    		d(detaching) {
    			if (detaching) detach(button);
    		}
    	};
    }

    // (49:0) {#if type === 'installed'}
    function create_if_block$7(ctx) {
    	let span;
    	let img;
    	let img_src_value;
    	let img_class_value;

    	return {
    		c() {
    			span = element("span");
    			img = element("img");
    			if (!src_url_equal(img.src, img_src_value = "" + (FULL_MODULE_PATH + "/images/" + /*path*/ ctx[3] + (DARK_COLOR_SCHEME ? '--dark-color-scheme' : '') + ".svg"))) attr(img, "src", img_src_value);
    			attr(img, "class", img_class_value = `pb-icon pb-icon--${/*variant*/ ctx[1]} pb-icon--${/*type*/ ctx[0]} ${/*classes*/ ctx[2]}`);
    			attr(img, "alt", "");
    			attr(span, "class", "pb-project__status-icon-span");
    			attr(span, "title", /*title*/ ctx[6]);
    		},
    		m(target, anchor) {
    			insert(target, span, anchor);
    			append(span, img);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*path*/ 8 && !src_url_equal(img.src, img_src_value = "" + (FULL_MODULE_PATH + "/images/" + /*path*/ ctx[3] + (DARK_COLOR_SCHEME ? '--dark-color-scheme' : '') + ".svg"))) {
    				attr(img, "src", img_src_value);
    			}

    			if (dirty & /*variant, type, classes*/ 7 && img_class_value !== (img_class_value = `pb-icon pb-icon--${/*variant*/ ctx[1]} pb-icon--${/*type*/ ctx[0]} ${/*classes*/ ctx[2]}`)) {
    				attr(img, "class", img_class_value);
    			}
    		},
    		d(detaching) {
    			if (detaching) detach(span);
    		}
    	};
    }

    function create_fragment$9(ctx) {
    	let if_block_anchor;

    	function select_block_type(ctx, dirty) {
    		if (/*type*/ ctx[0] === 'installed') return create_if_block$7;
    		return create_else_block$3;
    	}

    	let current_block_type = select_block_type(ctx);
    	let if_block = current_block_type(ctx);

    	return {
    		c() {
    			if_block.c();
    			if_block_anchor = empty();
    		},
    		m(target, anchor) {
    			if_block.m(target, anchor);
    			insert(target, if_block_anchor, anchor);
    		},
    		p(ctx, [dirty]) {
    			if (current_block_type === (current_block_type = select_block_type(ctx)) && if_block) {
    				if_block.p(ctx, dirty);
    			} else {
    				if_block.d(1);
    				if_block = current_block_type(ctx);

    				if (if_block) {
    					if_block.c();
    					if_block.m(if_block_anchor.parentNode, if_block_anchor);
    				}
    			}
    		},
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if_block.d(detaching);
    			if (detaching) detach(if_block_anchor);
    		}
    	};
    }

    function instance$9($$self, $$props, $$invalidate) {
    	let { type = '' } = $$props;
    	let { variant = false } = $$props;
    	let { classes = false } = $$props;

    	const typeToImg = {
    		status: {
    			path: 'blue-security-shield-icon',
    			alt: window.Drupal.t('Security Note'),
    			title: window.Drupal.t('Stable releases for this project are covered by the security advisory policy.')
    		},
    		usage: {
    			path: 'project-usage-icon',
    			alt: window.Drupal.t('Project Usage'),
    			title: window.Drupal.t('Shows the number of sites that use this project.')
    		},
    		compatible: {
    			path: 'compatible-icon',
    			alt: window.Drupal.t('Compatible'),
    			title: window.Drupal.t('This project is compatible with your version of Drupal.')
    		},
    		maintained: {
    			path: 'green-maintained-wrench-icon',
    			alt: window.Drupal.t('Well maintained'),
    			title: window.Drupal.t('This project is actively maintained by maintainers.')
    		},
    		installed: {
    			path: 'installed-check-icon',
    			alt: window.Drupal.t('Installed'),
    			title: window.Drupal.t('This project is installed.')
    		}
    	};

    	const { alt, title } = typeToImg[type];
    	let { path } = typeToImg[type];

    	// Change the path when gin dark theme is enabled.
    	if (document.querySelector('.gin--dark-mode') && type === 'installed') {
    		path = 'green-checkmark-icon';
    	}

    	$$self.$$set = $$props => {
    		if ('type' in $$props) $$invalidate(0, type = $$props.type);
    		if ('variant' in $$props) $$invalidate(1, variant = $$props.variant);
    		if ('classes' in $$props) $$invalidate(2, classes = $$props.classes);
    	};

    	return [type, variant, classes, path, typeToImg, alt, title];
    }

    class ProjectIcon extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$9, create_fragment$9, safe_not_equal, { type: 0, variant: 1, classes: 2 });
    	}
    }

    /* src/Project/ActionButton.svelte generated by Svelte v3.48.0 */

    function create_else_block$2(ctx) {
    	let span;
    	let current_block_type_index;
    	let if_block;
    	let current;
    	const if_block_creators = [create_if_block_3$3, create_if_block_8$1];
    	const if_blocks = [];

    	function select_block_type_1(ctx, dirty) {
    		if (PACKAGE_MANAGER) return 0;
    		if (/*project*/ ctx[0].commands) return 1;
    		return -1;
    	}

    	if (~(current_block_type_index = select_block_type_1(ctx))) {
    		if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);
    	}

    	return {
    		c() {
    			span = element("span");
    			if (if_block) if_block.c();
    		},
    		m(target, anchor) {
    			insert(target, span, anchor);

    			if (~current_block_type_index) {
    				if_blocks[current_block_type_index].m(span, null);
    			}

    			current = true;
    		},
    		p(ctx, dirty) {
    			let previous_block_index = current_block_type_index;
    			current_block_type_index = select_block_type_1(ctx);

    			if (current_block_type_index === previous_block_index) {
    				if (~current_block_type_index) {
    					if_blocks[current_block_type_index].p(ctx, dirty);
    				}
    			} else {
    				if (if_block) {
    					group_outros();

    					transition_out(if_blocks[previous_block_index], 1, 1, () => {
    						if_blocks[previous_block_index] = null;
    					});

    					check_outros();
    				}

    				if (~current_block_type_index) {
    					if_block = if_blocks[current_block_type_index];

    					if (!if_block) {
    						if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);
    						if_block.c();
    					} else {
    						if_block.p(ctx, dirty);
    					}

    					transition_in(if_block, 1);
    					if_block.m(span, null);
    				} else {
    					if_block = null;
    				}
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(if_block);
    			current = true;
    		},
    		o(local) {
    			transition_out(if_block);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(span);

    			if (~current_block_type_index) {
    				if_blocks[current_block_type_index].d();
    			}
    		}
    	};
    }

    // (53:40) 
    function create_if_block_1$5(ctx) {
    	let projectstatusindicator;
    	let t;
    	let if_block_anchor;
    	let current;

    	projectstatusindicator = new ProjectStatusIndicator({
    			props: {
    				project: /*project*/ ctx[0],
    				statusText: window.Drupal.t('Installed'),
    				$$slots: { default: [create_default_slot$2] },
    				$$scope: { ctx }
    			}
    		});

    	let if_block = /*project*/ ctx[0].tasks.length > 0 && create_if_block_2$3(ctx);

    	return {
    		c() {
    			create_component(projectstatusindicator.$$.fragment);
    			t = space();
    			if (if_block) if_block.c();
    			if_block_anchor = empty();
    		},
    		m(target, anchor) {
    			mount_component(projectstatusindicator, target, anchor);
    			insert(target, t, anchor);
    			if (if_block) if_block.m(target, anchor);
    			insert(target, if_block_anchor, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const projectstatusindicator_changes = {};
    			if (dirty & /*project*/ 1) projectstatusindicator_changes.project = /*project*/ ctx[0];

    			if (dirty & /*$$scope*/ 1024) {
    				projectstatusindicator_changes.$$scope = { dirty, ctx };
    			}

    			projectstatusindicator.$set(projectstatusindicator_changes);

    			if (/*project*/ ctx[0].tasks.length > 0) {
    				if (if_block) {
    					if_block.p(ctx, dirty);

    					if (dirty & /*project*/ 1) {
    						transition_in(if_block, 1);
    					}
    				} else {
    					if_block = create_if_block_2$3(ctx);
    					if_block.c();
    					transition_in(if_block, 1);
    					if_block.m(if_block_anchor.parentNode, if_block_anchor);
    				}
    			} else if (if_block) {
    				group_outros();

    				transition_out(if_block, 1, 1, () => {
    					if_block = null;
    				});

    				check_outros();
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(projectstatusindicator.$$.fragment, local);
    			transition_in(if_block);
    			current = true;
    		},
    		o(local) {
    			transition_out(projectstatusindicator.$$.fragment, local);
    			transition_out(if_block);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(projectstatusindicator, detaching);
    			if (detaching) detach(t);
    			if (if_block) if_block.d(detaching);
    			if (detaching) detach(if_block_anchor);
    		}
    	};
    }

    // (51:2) {#if !project.is_compatible}
    function create_if_block$6(ctx) {
    	let projectstatusindicator;
    	let current;

    	projectstatusindicator = new ProjectStatusIndicator({
    			props: {
    				project: /*project*/ ctx[0],
    				statusText: window.Drupal.t('Not compatible')
    			}
    		});

    	return {
    		c() {
    			create_component(projectstatusindicator.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(projectstatusindicator, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const projectstatusindicator_changes = {};
    			if (dirty & /*project*/ 1) projectstatusindicator_changes.project = /*project*/ ctx[0];
    			projectstatusindicator.$set(projectstatusindicator_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(projectstatusindicator.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projectstatusindicator.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(projectstatusindicator, detaching);
    		}
    	};
    }

    // (102:33) 
    function create_if_block_8$1(ctx) {
    	let show_if;
    	let current_block_type_index;
    	let if_block;
    	let if_block_anchor;
    	let current;
    	const if_block_creators = [create_if_block_9$1, create_else_block_3];
    	const if_blocks = [];

    	function select_block_type_4(ctx, dirty) {
    		if (dirty & /*project*/ 1) show_if = null;
    		if (show_if == null) show_if = !!/*project*/ ctx[0].commands.match(/^https?:\/\//);
    		if (show_if) return 0;
    		return 1;
    	}

    	current_block_type_index = select_block_type_4(ctx, -1);
    	if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);

    	return {
    		c() {
    			if_block.c();
    			if_block_anchor = empty();
    		},
    		m(target, anchor) {
    			if_blocks[current_block_type_index].m(target, anchor);
    			insert(target, if_block_anchor, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			let previous_block_index = current_block_type_index;
    			current_block_type_index = select_block_type_4(ctx, dirty);

    			if (current_block_type_index === previous_block_index) {
    				if_blocks[current_block_type_index].p(ctx, dirty);
    			} else {
    				group_outros();

    				transition_out(if_blocks[previous_block_index], 1, 1, () => {
    					if_blocks[previous_block_index] = null;
    				});

    				check_outros();
    				if_block = if_blocks[current_block_type_index];

    				if (!if_block) {
    					if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);
    					if_block.c();
    				} else {
    					if_block.p(ctx, dirty);
    				}

    				transition_in(if_block, 1);
    				if_block.m(if_block_anchor.parentNode, if_block_anchor);
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(if_block);
    			current = true;
    		},
    		o(local) {
    			transition_out(if_block);
    			current = false;
    		},
    		d(detaching) {
    			if_blocks[current_block_type_index].d(detaching);
    			if (detaching) detach(if_block_anchor);
    		}
    	};
    }

    // (62:6) {#if PACKAGE_MANAGER}
    function create_if_block_3$3(ctx) {
    	let current_block_type_index;
    	let if_block;
    	let if_block_anchor;
    	let current;
    	const if_block_creators = [create_if_block_4$2, create_if_block_5$1, create_else_block_1];
    	const if_blocks = [];

    	function select_block_type_2(ctx, dirty) {
    		if (/*isInInstallList*/ ctx[2] && !/*processMultipleProjects*/ ctx[3]) return 0;
    		if (/*InstallListFull*/ ctx[1] && !/*isInInstallList*/ ctx[2] && /*processMultipleProjects*/ ctx[3]) return 1;
    		return 2;
    	}

    	current_block_type_index = select_block_type_2(ctx);
    	if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);

    	return {
    		c() {
    			if_block.c();
    			if_block_anchor = empty();
    		},
    		m(target, anchor) {
    			if_blocks[current_block_type_index].m(target, anchor);
    			insert(target, if_block_anchor, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			let previous_block_index = current_block_type_index;
    			current_block_type_index = select_block_type_2(ctx);

    			if (current_block_type_index === previous_block_index) {
    				if_blocks[current_block_type_index].p(ctx, dirty);
    			} else {
    				group_outros();

    				transition_out(if_blocks[previous_block_index], 1, 1, () => {
    					if_blocks[previous_block_index] = null;
    				});

    				check_outros();
    				if_block = if_blocks[current_block_type_index];

    				if (!if_block) {
    					if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);
    					if_block.c();
    				} else {
    					if_block.p(ctx, dirty);
    				}

    				transition_in(if_block, 1);
    				if_block.m(if_block_anchor.parentNode, if_block_anchor);
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(if_block);
    			current = true;
    		},
    		o(local) {
    			transition_out(if_block);
    			current = false;
    		},
    		d(detaching) {
    			if_blocks[current_block_type_index].d(detaching);
    			if (detaching) detach(if_block_anchor);
    		}
    	};
    }

    // (107:8) {:else}
    function create_else_block_3(ctx) {
    	let projectbuttonbase;
    	let current;

    	projectbuttonbase = new ProjectButtonBase({
    			props: {
    				"aria-haspopup": "dialog",
    				click: /*func*/ ctx[6],
    				$$slots: { default: [create_default_slot_5] },
    				$$scope: { ctx }
    			}
    		});

    	return {
    		c() {
    			create_component(projectbuttonbase.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(projectbuttonbase, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const projectbuttonbase_changes = {};
    			if (dirty & /*project*/ 1) projectbuttonbase_changes.click = /*func*/ ctx[6];

    			if (dirty & /*$$scope, project*/ 1025) {
    				projectbuttonbase_changes.$$scope = { dirty, ctx };
    			}

    			projectbuttonbase.$set(projectbuttonbase_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(projectbuttonbase.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projectbuttonbase.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(projectbuttonbase, detaching);
    		}
    	};
    }

    // (103:8) {#if project.commands.match(/^https?:\/\//)}
    function create_if_block_9$1(ctx) {
    	let a;
    	let projectbuttonbase;
    	let a_href_value;
    	let current;

    	projectbuttonbase = new ProjectButtonBase({
    			props: {
    				$$slots: { default: [create_default_slot_4] },
    				$$scope: { ctx }
    			}
    		});

    	return {
    		c() {
    			a = element("a");
    			create_component(projectbuttonbase.$$.fragment);
    			attr(a, "href", a_href_value = /*project*/ ctx[0].commands);
    			attr(a, "target", "_blank");
    			attr(a, "rel", "noreferrer");
    		},
    		m(target, anchor) {
    			insert(target, a, anchor);
    			mount_component(projectbuttonbase, a, null);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const projectbuttonbase_changes = {};

    			if (dirty & /*$$scope*/ 1024) {
    				projectbuttonbase_changes.$$scope = { dirty, ctx };
    			}

    			projectbuttonbase.$set(projectbuttonbase_changes);

    			if (!current || dirty & /*project*/ 1 && a_href_value !== (a_href_value = /*project*/ ctx[0].commands)) {
    				attr(a, "href", a_href_value);
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(projectbuttonbase.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projectbuttonbase.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(a);
    			destroy_component(projectbuttonbase);
    		}
    	};
    }

    // (108:10) <ProjectButtonBase             aria-haspopup="dialog"             click={() => openPopup(getCommandsPopupMessage(project), project)}           >
    function create_default_slot_5(ctx) {
    	let html_tag;
    	let raw_value = window.Drupal.t('View Commands <span class="visually-hidden">for @title</span>', { '@title': /*project*/ ctx[0].title }) + "";
    	let html_anchor;

    	return {
    		c() {
    			html_tag = new HtmlTag(false);
    			html_anchor = empty();
    			html_tag.a = html_anchor;
    		},
    		m(target, anchor) {
    			html_tag.m(raw_value, target, anchor);
    			insert(target, html_anchor, anchor);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*project*/ 1 && raw_value !== (raw_value = window.Drupal.t('View Commands <span class="visually-hidden">for @title</span>', { '@title': /*project*/ ctx[0].title }) + "")) html_tag.p(raw_value);
    		},
    		d(detaching) {
    			if (detaching) detach(html_anchor);
    			if (detaching) html_tag.d();
    		}
    	};
    }

    // (105:13) <ProjectButtonBase>
    function create_default_slot_4(ctx) {
    	let t_value = window.Drupal.t('Install') + "";
    	let t;

    	return {
    		c() {
    			t = text(t_value);
    		},
    		m(target, anchor) {
    			insert(target, t, anchor);
    		},
    		p: noop,
    		d(detaching) {
    			if (detaching) detach(t);
    		}
    	};
    }

    // (76:8) {:else}
    function create_else_block_1(ctx) {
    	let projectbuttonbase;
    	let current;

    	projectbuttonbase = new ProjectButtonBase({
    			props: {
    				click: /*onClick*/ ctx[4],
    				$$slots: { default: [create_default_slot_3] },
    				$$scope: { ctx }
    			}
    		});

    	return {
    		c() {
    			create_component(projectbuttonbase.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(projectbuttonbase, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const projectbuttonbase_changes = {};

    			if (dirty & /*$$scope, project, isInInstallList*/ 1029) {
    				projectbuttonbase_changes.$$scope = { dirty, ctx };
    			}

    			projectbuttonbase.$set(projectbuttonbase_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(projectbuttonbase.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projectbuttonbase.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(projectbuttonbase, detaching);
    		}
    	};
    }

    // (67:81) 
    function create_if_block_5$1(ctx) {
    	let projectbuttonbase;
    	let current;

    	projectbuttonbase = new ProjectButtonBase({
    			props: {
    				disabled: true,
    				$$slots: { default: [create_default_slot_2] },
    				$$scope: { ctx }
    			}
    		});

    	return {
    		c() {
    			create_component(projectbuttonbase.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(projectbuttonbase, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const projectbuttonbase_changes = {};

    			if (dirty & /*$$scope, project*/ 1025) {
    				projectbuttonbase_changes.$$scope = { dirty, ctx };
    			}

    			projectbuttonbase.$set(projectbuttonbase_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(projectbuttonbase.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projectbuttonbase.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(projectbuttonbase, detaching);
    		}
    	};
    }

    // (63:8) {#if isInInstallList && !processMultipleProjects}
    function create_if_block_4$2(ctx) {
    	let projectbuttonbase;
    	let current;

    	projectbuttonbase = new ProjectButtonBase({
    			props: {
    				$$slots: { default: [create_default_slot_1$1] },
    				$$scope: { ctx }
    			}
    		});

    	return {
    		c() {
    			create_component(projectbuttonbase.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(projectbuttonbase, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const projectbuttonbase_changes = {};

    			if (dirty & /*$$scope*/ 1024) {
    				projectbuttonbase_changes.$$scope = { dirty, ctx };
    			}

    			projectbuttonbase.$set(projectbuttonbase_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(projectbuttonbase.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projectbuttonbase.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(projectbuttonbase, detaching);
    		}
    	};
    }

    // (92:12) {:else}
    function create_else_block_2(ctx) {
    	let html_tag;
    	let raw_value = window.Drupal.t('Install <span class="visually-hidden">@title</span>', { '@title': /*project*/ ctx[0].title }) + "";
    	let html_anchor;

    	return {
    		c() {
    			html_tag = new HtmlTag(false);
    			html_anchor = empty();
    			html_tag.a = html_anchor;
    		},
    		m(target, anchor) {
    			html_tag.m(raw_value, target, anchor);
    			insert(target, html_anchor, anchor);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*project*/ 1 && raw_value !== (raw_value = window.Drupal.t('Install <span class="visually-hidden">@title</span>', { '@title': /*project*/ ctx[0].title }) + "")) html_tag.p(raw_value);
    		},
    		d(detaching) {
    			if (detaching) detach(html_anchor);
    			if (detaching) html_tag.d();
    		}
    	};
    }

    // (85:46) 
    function create_if_block_7$1(ctx) {
    	let html_tag;
    	let raw_value = window.Drupal.t('Select <span class="visually-hidden">@title</span>', { '@title': /*project*/ ctx[0].title }) + "";
    	let html_anchor;

    	return {
    		c() {
    			html_tag = new HtmlTag(false);
    			html_anchor = empty();
    			html_tag.a = html_anchor;
    		},
    		m(target, anchor) {
    			html_tag.m(raw_value, target, anchor);
    			insert(target, html_anchor, anchor);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*project*/ 1 && raw_value !== (raw_value = window.Drupal.t('Select <span class="visually-hidden">@title</span>', { '@title': /*project*/ ctx[0].title }) + "")) html_tag.p(raw_value);
    		},
    		d(detaching) {
    			if (detaching) detach(html_anchor);
    			if (detaching) html_tag.d();
    		}
    	};
    }

    // (78:12) {#if isInInstallList}
    function create_if_block_6$1(ctx) {
    	let html_tag;
    	let raw_value = window.Drupal.t('Deselect <span class="visually-hidden">@title</span>', { '@title': /*project*/ ctx[0].title }) + "";
    	let html_anchor;

    	return {
    		c() {
    			html_tag = new HtmlTag(false);
    			html_anchor = empty();
    			html_tag.a = html_anchor;
    		},
    		m(target, anchor) {
    			html_tag.m(raw_value, target, anchor);
    			insert(target, html_anchor, anchor);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*project*/ 1 && raw_value !== (raw_value = window.Drupal.t('Deselect <span class="visually-hidden">@title</span>', { '@title': /*project*/ ctx[0].title }) + "")) html_tag.p(raw_value);
    		},
    		d(detaching) {
    			if (detaching) detach(html_anchor);
    			if (detaching) html_tag.d();
    		}
    	};
    }

    // (77:10) <ProjectButtonBase click={onClick}>
    function create_default_slot_3(ctx) {
    	let if_block_anchor;

    	function select_block_type_3(ctx, dirty) {
    		if (/*isInInstallList*/ ctx[2]) return create_if_block_6$1;
    		if (/*processMultipleProjects*/ ctx[3]) return create_if_block_7$1;
    		return create_else_block_2;
    	}

    	let current_block_type = select_block_type_3(ctx);
    	let if_block = current_block_type(ctx);

    	return {
    		c() {
    			if_block.c();
    			if_block_anchor = empty();
    		},
    		m(target, anchor) {
    			if_block.m(target, anchor);
    			insert(target, if_block_anchor, anchor);
    		},
    		p(ctx, dirty) {
    			if (current_block_type === (current_block_type = select_block_type_3(ctx)) && if_block) {
    				if_block.p(ctx, dirty);
    			} else {
    				if_block.d(1);
    				if_block = current_block_type(ctx);

    				if (if_block) {
    					if_block.c();
    					if_block.m(if_block_anchor.parentNode, if_block_anchor);
    				}
    			}
    		},
    		d(detaching) {
    			if_block.d(detaching);
    			if (detaching) detach(if_block_anchor);
    		}
    	};
    }

    // (68:10) <ProjectButtonBase disabled>
    function create_default_slot_2(ctx) {
    	let html_tag;
    	let raw_value = window.Drupal.t('Select <span class="visually-hidden">@title</span>', { '@title': /*project*/ ctx[0].title }) + "";
    	let html_anchor;

    	return {
    		c() {
    			html_tag = new HtmlTag(false);
    			html_anchor = empty();
    			html_tag.a = html_anchor;
    		},
    		m(target, anchor) {
    			html_tag.m(raw_value, target, anchor);
    			insert(target, html_anchor, anchor);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*project*/ 1 && raw_value !== (raw_value = window.Drupal.t('Select <span class="visually-hidden">@title</span>', { '@title': /*project*/ ctx[0].title }) + "")) html_tag.p(raw_value);
    		},
    		d(detaching) {
    			if (detaching) detach(html_anchor);
    			if (detaching) html_tag.d();
    		}
    	};
    }

    // (64:10) <ProjectButtonBase>
    function create_default_slot_1$1(ctx) {
    	let loadingellipsis;
    	let current;
    	loadingellipsis = new LoadingEllipsis({});

    	return {
    		c() {
    			create_component(loadingellipsis.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(loadingellipsis, target, anchor);
    			current = true;
    		},
    		i(local) {
    			if (current) return;
    			transition_in(loadingellipsis.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(loadingellipsis.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(loadingellipsis, detaching);
    		}
    	};
    }

    // (54:4) <ProjectStatusIndicator {project} statusText={window.Drupal.t('Installed')}>
    function create_default_slot$2(ctx) {
    	let projecticon;
    	let current;
    	projecticon = new ProjectIcon({ props: { type: "installed" } });

    	return {
    		c() {
    			create_component(projecticon.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(projecticon, target, anchor);
    			current = true;
    		},
    		p: noop,
    		i(local) {
    			if (current) return;
    			transition_in(projecticon.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projecticon.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(projecticon, detaching);
    		}
    	};
    }

    // (57:4) {#if project.tasks.length > 0}
    function create_if_block_2$3(ctx) {
    	let dropbutton;
    	let current;

    	dropbutton = new DropButton({
    			props: { tasks: /*project*/ ctx[0].tasks }
    		});

    	return {
    		c() {
    			create_component(dropbutton.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(dropbutton, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const dropbutton_changes = {};
    			if (dirty & /*project*/ 1) dropbutton_changes.tasks = /*project*/ ctx[0].tasks;
    			dropbutton.$set(dropbutton_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(dropbutton.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(dropbutton.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(dropbutton, detaching);
    		}
    	};
    }

    function create_fragment$8(ctx) {
    	let div;
    	let current_block_type_index;
    	let if_block;
    	let current;
    	const if_block_creators = [create_if_block$6, create_if_block_1$5, create_else_block$2];
    	const if_blocks = [];

    	function select_block_type(ctx, dirty) {
    		if (!/*project*/ ctx[0].is_compatible) return 0;
    		if (/*project*/ ctx[0].status === 'active') return 1;
    		return 2;
    	}

    	current_block_type_index = select_block_type(ctx);
    	if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);

    	return {
    		c() {
    			div = element("div");
    			if_block.c();
    			attr(div, "class", "pb-actions");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			if_blocks[current_block_type_index].m(div, null);
    			current = true;
    		},
    		p(ctx, [dirty]) {
    			let previous_block_index = current_block_type_index;
    			current_block_type_index = select_block_type(ctx);

    			if (current_block_type_index === previous_block_index) {
    				if_blocks[current_block_type_index].p(ctx, dirty);
    			} else {
    				group_outros();

    				transition_out(if_blocks[previous_block_index], 1, 1, () => {
    					if_blocks[previous_block_index] = null;
    				});

    				check_outros();
    				if_block = if_blocks[current_block_type_index];

    				if (!if_block) {
    					if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);
    					if_block.c();
    				} else {
    					if_block.p(ctx, dirty);
    				}

    				transition_in(if_block, 1);
    				if_block.m(div, null);
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(if_block);
    			current = true;
    		},
    		o(local) {
    			transition_out(if_block);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    			if_blocks[current_block_type_index].d();
    		}
    	};
    }

    function instance$8($$self, $$props, $$invalidate) {
    	let isInInstallList;
    	let $installList;
    	component_subscribe($$self, installList, $$value => $$invalidate(5, $installList = $$value));
    	let { project } = $$props;
    	let InstallListFull;
    	const processMultipleProjects = MAX_SELECTIONS === null || MAX_SELECTIONS > 1;

    	function handleAddToInstallListClick(singleProject) {
    		addToInstallList(singleProject);
    	}

    	function handleRemoveFromInstallList(projectId) {
    		removeFromInstallList(projectId);
    	}

    	const onClick = async () => {
    		if (processMultipleProjects) {
    			if (isInInstallList) {
    				handleRemoveFromInstallList(project.id);
    			} else {
    				handleAddToInstallListClick(project);
    			}
    		} else {
    			handleAddToInstallListClick(project);
    			await processInstallList();
    		}
    	};

    	const func = () => openPopup(getCommandsPopupMessage(project), project);

    	$$self.$$set = $$props => {
    		if ('project' in $$props) $$invalidate(0, project = $$props.project);
    	};

    	$$self.$$.update = () => {
    		if ($$self.$$.dirty & /*$installList, project*/ 33) {
    			$$invalidate(2, isInInstallList = $installList.some(item => item.id === project.id));
    		}

    		if ($$self.$$.dirty & /*$installList*/ 32) {
    			// If MAX_SELECTIONS is null (no limit), then the install list is never full.
    			$$invalidate(1, InstallListFull = $installList.length === MAX_SELECTIONS);
    		}
    	};

    	return [
    		project,
    		InstallListFull,
    		isInInstallList,
    		processMultipleProjects,
    		onClick,
    		$installList,
    		func
    	];
    }

    class ActionButton extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$8, create_fragment$8, safe_not_equal, { project: 0 });
    	}
    }

    /* src/Project/Image.svelte generated by Svelte v3.48.0 */

    function create_else_block$1(ctx) {
    	let img;
    	let img_levels = [/*defaultImgProps*/ ctx[4](/*fallbackImage*/ ctx[2])];
    	let img_data = {};

    	for (let i = 0; i < img_levels.length; i += 1) {
    		img_data = assign(img_data, img_levels[i]);
    	}

    	return {
    		c() {
    			img = element("img");
    			set_attributes(img, img_data);
    		},
    		m(target, anchor) {
    			insert(target, img, anchor);
    		},
    		p(ctx, dirty) {
    			set_attributes(img, img_data = get_spread_update(img_levels, [/*defaultImgProps*/ ctx[4](/*fallbackImage*/ ctx[2])]));
    		},
    		d(detaching) {
    			if (detaching) detach(img);
    		}
    	};
    }

    // (34:0) {#if normalizedSources.length > index}
    function create_if_block$5(ctx) {
    	let img;
    	let img_src_value;
    	let img_alt_value;
    	let img_class_value;
    	let mounted;
    	let dispose;

    	return {
    		c() {
    			img = element("img");
    			if (!src_url_equal(img.src, img_src_value = /*normalizedSources*/ ctx[1][/*index*/ ctx[0]].file)) attr(img, "src", img_src_value);
    			attr(img, "alt", img_alt_value = /*normalizedSources*/ ctx[1][/*index*/ ctx[0]].alt);
    			attr(img, "class", img_class_value = /*$$props*/ ctx[5].class);
    		},
    		m(target, anchor) {
    			insert(target, img, anchor);

    			if (!mounted) {
    				dispose = listen(img, "error", /*showFallback*/ ctx[3]);
    				mounted = true;
    			}
    		},
    		p(ctx, dirty) {
    			if (dirty & /*index*/ 1 && !src_url_equal(img.src, img_src_value = /*normalizedSources*/ ctx[1][/*index*/ ctx[0]].file)) {
    				attr(img, "src", img_src_value);
    			}

    			if (dirty & /*index*/ 1 && img_alt_value !== (img_alt_value = /*normalizedSources*/ ctx[1][/*index*/ ctx[0]].alt)) {
    				attr(img, "alt", img_alt_value);
    			}

    			if (dirty & /*$$props*/ 32 && img_class_value !== (img_class_value = /*$$props*/ ctx[5].class)) {
    				attr(img, "class", img_class_value);
    			}
    		},
    		d(detaching) {
    			if (detaching) detach(img);
    			mounted = false;
    			dispose();
    		}
    	};
    }

    function create_fragment$7(ctx) {
    	let if_block_anchor;

    	function select_block_type(ctx, dirty) {
    		if (/*normalizedSources*/ ctx[1].length > /*index*/ ctx[0]) return create_if_block$5;
    		return create_else_block$1;
    	}

    	let current_block_type = select_block_type(ctx);
    	let if_block = current_block_type(ctx);

    	return {
    		c() {
    			if_block.c();
    			if_block_anchor = empty();
    		},
    		m(target, anchor) {
    			if_block.m(target, anchor);
    			insert(target, if_block_anchor, anchor);
    		},
    		p(ctx, [dirty]) {
    			if (current_block_type === (current_block_type = select_block_type(ctx)) && if_block) {
    				if_block.p(ctx, dirty);
    			} else {
    				if_block.d(1);
    				if_block = current_block_type(ctx);

    				if (if_block) {
    					if_block.c();
    					if_block.m(if_block_anchor.parentNode, if_block_anchor);
    				}
    			}
    		},
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if_block.d(detaching);
    			if (detaching) detach(if_block_anchor);
    		}
    	};
    }

    function instance$7($$self, $$props, $$invalidate) {
    	let { sources } = $$props;
    	let { index = 0 } = $$props;
    	const normalizedSources = sources ? [sources].flat() : [];
    	const fallbackImage = `${FULL_MODULE_PATH}/images/puzzle-piece-placeholder.svg`;

    	const showFallback = ev => {
    		ev.target.src = fallbackImage;
    	};

    	/**
     * Props for the images used in the carousel.
     *
     * @param {string} src
     *   The source attribute.
     * @param {string} alt
     *   The alt attribute, defaults to 'Placeholder' if undefined.
     *
     * @return {{src, alt: string, class: string}}
     *   An object of element attributes
     */
    	const defaultImgProps = (src, alt) => ({
    		src,
    		alt: typeof alt !== 'undefined'
    		? alt
    		: window.Drupal.t('Placeholder'),
    		class: `${$$props.class} `
    	});

    	$$self.$$set = $$new_props => {
    		$$invalidate(5, $$props = assign(assign({}, $$props), exclude_internal_props($$new_props)));
    		if ('sources' in $$new_props) $$invalidate(6, sources = $$new_props.sources);
    		if ('index' in $$new_props) $$invalidate(0, index = $$new_props.index);
    	};

    	$$props = exclude_internal_props($$props);

    	return [
    		index,
    		normalizedSources,
    		fallbackImage,
    		showFallback,
    		defaultImgProps,
    		$$props,
    		sources
    	];
    }

    class Image extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$7, create_fragment$7, safe_not_equal, { sources: 6, index: 0 });
    	}
    }

    /* src/ImageCarousel.svelte generated by Svelte v3.48.0 */

    function create_if_block_1$4(ctx) {
    	let button;
    	let img;
    	let mounted;
    	let dispose;
    	let img_levels = [/*imgProps*/ ctx[4]('left')];
    	let img_data = {};

    	for (let i = 0; i < img_levels.length; i += 1) {
    		img_data = assign(img_data, img_levels[i]);
    	}

    	let button_levels = [/*buttonProps*/ ctx[3]('left')];
    	let button_data = {};

    	for (let i = 0; i < button_levels.length; i += 1) {
    		button_data = assign(button_data, button_levels[i]);
    	}

    	return {
    		c() {
    			button = element("button");
    			img = element("img");
    			set_attributes(img, img_data);
    			set_attributes(button, button_data);
    		},
    		m(target, anchor) {
    			insert(target, button, anchor);
    			append(button, img);
    			if (button.autofocus) button.focus();

    			if (!mounted) {
    				dispose = listen(button, "click", /*click_handler*/ ctx[5]);
    				mounted = true;
    			}
    		},
    		p(ctx, dirty) {
    			set_attributes(img, img_data = get_spread_update(img_levels, [/*imgProps*/ ctx[4]('left')]));
    			set_attributes(button, button_data = get_spread_update(button_levels, [/*buttonProps*/ ctx[3]('left')]));
    		},
    		d(detaching) {
    			if (detaching) detach(button);
    			mounted = false;
    			dispose();
    		}
    	};
    }

    // (64:2) {#if sources.length}
    function create_if_block$4(ctx) {
    	let button;
    	let img;
    	let mounted;
    	let dispose;
    	let img_levels = [/*imgProps*/ ctx[4]('right')];
    	let img_data = {};

    	for (let i = 0; i < img_levels.length; i += 1) {
    		img_data = assign(img_data, img_levels[i]);
    	}

    	let button_levels = [/*buttonProps*/ ctx[3]('right')];
    	let button_data = {};

    	for (let i = 0; i < button_levels.length; i += 1) {
    		button_data = assign(button_data, button_levels[i]);
    	}

    	return {
    		c() {
    			button = element("button");
    			img = element("img");
    			set_attributes(img, img_data);
    			set_attributes(button, button_data);
    		},
    		m(target, anchor) {
    			insert(target, button, anchor);
    			append(button, img);
    			if (button.autofocus) button.focus();

    			if (!mounted) {
    				dispose = listen(button, "click", /*click_handler_1*/ ctx[6]);
    				mounted = true;
    			}
    		},
    		p(ctx, dirty) {
    			set_attributes(img, img_data = get_spread_update(img_levels, [/*imgProps*/ ctx[4]('right')]));
    			set_attributes(button, button_data = get_spread_update(button_levels, [/*buttonProps*/ ctx[3]('right')]));
    		},
    		d(detaching) {
    			if (detaching) detach(button);
    			mounted = false;
    			dispose();
    		}
    	};
    }

    function create_fragment$6(ctx) {
    	let div;
    	let t0;
    	let image;
    	let t1;
    	let current;
    	let if_block0 = /*sources*/ ctx[0].length && create_if_block_1$4(ctx);

    	image = new Image({
    			props: {
    				sources: /*sources*/ ctx[0],
    				index: /*index*/ ctx[1],
    				class: "pb-image-carousel__slide"
    			}
    		});

    	let if_block1 = /*sources*/ ctx[0].length && create_if_block$4(ctx);

    	return {
    		c() {
    			div = element("div");
    			if (if_block0) if_block0.c();
    			t0 = space();
    			create_component(image.$$.fragment);
    			t1 = space();
    			if (if_block1) if_block1.c();
    			attr(div, "class", "pb-image-carousel");
    			attr(div, "aria-hidden", /*missingAltText*/ ctx[2]());
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			if (if_block0) if_block0.m(div, null);
    			append(div, t0);
    			mount_component(image, div, null);
    			append(div, t1);
    			if (if_block1) if_block1.m(div, null);
    			current = true;
    		},
    		p(ctx, [dirty]) {
    			if (/*sources*/ ctx[0].length) {
    				if (if_block0) {
    					if_block0.p(ctx, dirty);
    				} else {
    					if_block0 = create_if_block_1$4(ctx);
    					if_block0.c();
    					if_block0.m(div, t0);
    				}
    			} else if (if_block0) {
    				if_block0.d(1);
    				if_block0 = null;
    			}

    			const image_changes = {};
    			if (dirty & /*sources*/ 1) image_changes.sources = /*sources*/ ctx[0];
    			if (dirty & /*index*/ 2) image_changes.index = /*index*/ ctx[1];
    			image.$set(image_changes);

    			if (/*sources*/ ctx[0].length) {
    				if (if_block1) {
    					if_block1.p(ctx, dirty);
    				} else {
    					if_block1 = create_if_block$4(ctx);
    					if_block1.c();
    					if_block1.m(div, null);
    				}
    			} else if (if_block1) {
    				if_block1.d(1);
    				if_block1 = null;
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(image.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(image.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    			if (if_block0) if_block0.d();
    			destroy_component(image);
    			if (if_block1) if_block1.d();
    		}
    	};
    }

    function instance$6($$self, $$props, $$invalidate) {
    	let { sources } = $$props;
    	let index = 0;
    	const missingAltText = () => !!sources.filter(src => !src.alt).length;

    	/**
     * Props for a slide next/previous button.
     *
     * @param {string} dir
     *   The direction of the button.
     * @return {{disabled: boolean, class: string}}
     *   The slide props.
     */
    	const buttonProps = dir => {
    		const isDisabled = dir === 'right'
    		? index === sources.length - 1
    		: index === 0;

    		const classes = [
    			'pb-image-carousel__btn',
    			`pb-image-carousel__btn--${dir}`,
    			isDisabled ? 'pb-image-carousel__btn--disabled' : ''
    		];

    		return {
    			class: classes.filter(className => !!className).join(' '),
    			disabled: isDisabled
    		};
    	};

    	/**
     * Props for a slide next/previous button image.
     *
     * @param {string} dir
     *   The direction of the button
     * @return {{src: string, alt: *}}
     *   The slide button Props
     */
    	const imgProps = dir => ({
    		class: 'pb-image-carousel__btn-icon',
    		src: `${FULL_MODULE_PATH}/images/slide-icon.svg`,
    		alt: dir === 'right'
    		? window.Drupal.t('Slide right')
    		: window.Drupal.t('Slide left')
    	});

    	const click_handler = () => {
    		$$invalidate(1, index = (index + sources.length - 1) % sources.length);
    	};

    	const click_handler_1 = () => {
    		$$invalidate(1, index = (index + 1) % sources.length);
    	};

    	$$self.$$set = $$props => {
    		if ('sources' in $$props) $$invalidate(0, sources = $$props.sources);
    	};

    	return [
    		sources,
    		index,
    		missingAltText,
    		buttonProps,
    		imgProps,
    		click_handler,
    		click_handler_1
    	];
    }

    class ImageCarousel extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$6, create_fragment$6, safe_not_equal, { sources: 0 });
    	}
    }

    // eslint-disable-next-line import/prefer-default-export
    const numberFormatter = new Intl.NumberFormat(navigator.language);

    /* src/DetailModal.svelte generated by Svelte v3.48.0 */

    function create_if_block_9(ctx) {
    	let div;
    	let strong;
    	let t1;
    	let span;
    	let t2_value = /*project*/ ctx[0].module_categories.map(func).join(', ') + "";
    	let t2;

    	return {
    		c() {
    			div = element("div");
    			strong = element("strong");
    			strong.textContent = "Categories:";
    			t1 = space();
    			span = element("span");
    			t2 = text(t2_value);
    			attr(div, "class", "pb-detail-modal__categories");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			append(div, strong);
    			append(div, t1);
    			append(div, span);
    			append(span, t2);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*project*/ 1 && t2_value !== (t2_value = /*project*/ ctx[0].module_categories.map(func).join(', ') + "")) set_data(t2, t2_value);
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    		}
    	};
    }

    // (48:4) {#if project.project_images.length > 0}
    function create_if_block_8(ctx) {
    	let div;
    	let imagecarousel;
    	let current;

    	imagecarousel = new ImageCarousel({
    			props: {
    				sources: /*project*/ ctx[0].project_images
    			}
    		});

    	return {
    		c() {
    			div = element("div");
    			create_component(imagecarousel.$$.fragment);
    			attr(div, "class", "pb-detail-modal__carousel-wrapper");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			mount_component(imagecarousel, div, null);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const imagecarousel_changes = {};
    			if (dirty & /*project*/ 1) imagecarousel_changes.sources = /*project*/ ctx[0].project_images;
    			imagecarousel.$set(imagecarousel_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(imagecarousel.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(imagecarousel.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    			destroy_component(imagecarousel);
    		}
    	};
    }

    // (53:4) {#if project.body.value}
    function create_if_block_7(ctx) {
    	let div;
    	let raw_value = /*project*/ ctx[0].body.value + "";

    	return {
    		c() {
    			div = element("div");
    			attr(div, "class", "pb-detail-modal__description");
    			attr(div, "id", "description-wrapper");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			div.innerHTML = raw_value;
    		},
    		p(ctx, dirty) {
    			if (dirty & /*project*/ 1 && raw_value !== (raw_value = /*project*/ ctx[0].body.value + "")) div.innerHTML = raw_value;		},
    		d(detaching) {
    			if (detaching) detach(div);
    		}
    	};
    }

    // (60:4) {#if PACKAGE_MANAGER}
    function create_if_block_6(ctx) {
    	let div;
    	let actionbutton;
    	let current;
    	actionbutton = new ActionButton({ props: { project: /*project*/ ctx[0] } });

    	return {
    		c() {
    			div = element("div");
    			create_component(actionbutton.$$.fragment);
    			attr(div, "class", "pb-detail-modal__view-commands pb-detail-modal__sidebar_element");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			mount_component(actionbutton, div, null);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const actionbutton_changes = {};
    			if (dirty & /*project*/ 1) actionbutton_changes.project = /*project*/ ctx[0];
    			actionbutton.$set(actionbutton_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(actionbutton.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(actionbutton.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    			destroy_component(actionbutton);
    		}
    	};
    }

    // (67:4) {#if project.is_compatible}
    function create_if_block_5(ctx) {
    	let div;
    	let projecticon;
    	let t0;
    	let t1_value = window.Drupal.t('Compatible with your Drupal installation') + "";
    	let t1;
    	let current;

    	projecticon = new ProjectIcon({
    			props: {
    				type: "compatible",
    				variant: "module-details",
    				classes: "pb-detail-modal__module-details-icon-sidebar"
    			}
    		});

    	return {
    		c() {
    			div = element("div");
    			create_component(projecticon.$$.fragment);
    			t0 = space();
    			t1 = text(t1_value);
    			attr(div, "class", "pb-detail-modal__sidebar_element");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			mount_component(projecticon, div, null);
    			append(div, t0);
    			append(div, t1);
    			current = true;
    		},
    		p: noop,
    		i(local) {
    			if (current) return;
    			transition_in(projecticon.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projecticon.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    			destroy_component(projecticon);
    		}
    	};
    }

    // (77:4) {#if !project.is_compatible}
    function create_if_block_4$1(ctx) {
    	let div;

    	return {
    		c() {
    			div = element("div");
    			div.textContent = `${window.Drupal.t('Not compatible with your Drupal installation')}`;
    			attr(div, "class", "pb-detail-modal__sidebar_element");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    		},
    		p: noop,
    		d(detaching) {
    			if (detaching) detach(div);
    		}
    	};
    }

    // (82:4) {#if typeof project.project_usage_total === 'number'}
    function create_if_block_3$2(ctx) {
    	let div;
    	let projecticon;
    	let t0;
    	let t1_value = /*Drupal*/ ctx[1].formatPlural(/*project*/ ctx[0].project_usage_total, `${numberFormatter.format(1)} site reports using this module`, `${numberFormatter.format(/*project*/ ctx[0].project_usage_total)} sites report using this module`) + "";
    	let t1;
    	let current;

    	projecticon = new ProjectIcon({
    			props: {
    				type: "usage",
    				variant: "module-details",
    				classes: "pb-detail-modal__module-details-icon-sidebar"
    			}
    		});

    	return {
    		c() {
    			div = element("div");
    			create_component(projecticon.$$.fragment);
    			t0 = space();
    			t1 = text(t1_value);
    			attr(div, "class", "pb-detail-modal__sidebar_element");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			mount_component(projecticon, div, null);
    			append(div, t0);
    			append(div, t1);
    			current = true;
    		},
    		p(ctx, dirty) {
    			if ((!current || dirty & /*project*/ 1) && t1_value !== (t1_value = /*Drupal*/ ctx[1].formatPlural(/*project*/ ctx[0].project_usage_total, `${numberFormatter.format(1)} site reports using this module`, `${numberFormatter.format(/*project*/ ctx[0].project_usage_total)} sites report using this module`) + "")) set_data(t1, t1_value);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(projecticon.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projecticon.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    			destroy_component(projecticon);
    		}
    	};
    }

    // (98:4) {#if project.is_covered}
    function create_if_block_2$2(ctx) {
    	let div;
    	let projecticon;
    	let t0;
    	let t1_value = window.Drupal.t('Stable releases for this project are covered by the security advisory policy') + "";
    	let t1;
    	let current;

    	projecticon = new ProjectIcon({
    			props: {
    				type: "status",
    				variant: "module-details",
    				classes: "pb-detail-modal__module-details-icon-sidebar"
    			}
    		});

    	return {
    		c() {
    			div = element("div");
    			create_component(projecticon.$$.fragment);
    			t0 = space();
    			t1 = text(t1_value);
    			attr(div, "class", "pb-detail-modal__sidebar_element");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			mount_component(projecticon, div, null);
    			append(div, t0);
    			append(div, t1);
    			current = true;
    		},
    		p: noop,
    		i(local) {
    			if (current) return;
    			transition_in(projecticon.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projecticon.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    			destroy_component(projecticon);
    		}
    	};
    }

    // (110:4) {#if project.is_maintained}
    function create_if_block_1$3(ctx) {
    	let div;
    	let projecticon;
    	let t0;
    	let t1_value = window.Drupal.t('The module is actively maintained by the maintainers') + "";
    	let t1;
    	let current;

    	projecticon = new ProjectIcon({
    			props: {
    				type: "maintained",
    				variant: "module-details",
    				classes: "pb-module-page__module-details-icon-sidebar"
    			}
    		});

    	return {
    		c() {
    			div = element("div");
    			create_component(projecticon.$$.fragment);
    			t0 = space();
    			t1 = text(t1_value);
    			attr(div, "class", "pb-module-page__sidebar_element");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			mount_component(projecticon, div, null);
    			append(div, t0);
    			append(div, t1);
    			current = true;
    		},
    		p: noop,
    		i(local) {
    			if (current) return;
    			transition_in(projecticon.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projecticon.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    			destroy_component(projecticon);
    		}
    	};
    }

    // (121:4) {#if project.url}
    function create_if_block$3(ctx) {
    	let div;
    	let button;
    	let t_value = window.Drupal.t('Learn more') + "";
    	let t;
    	let button_onclick_value;

    	return {
    		c() {
    			div = element("div");
    			button = element("button");
    			t = text(t_value);
    			attr(button, "class", "project__action_button");
    			attr(button, "onclick", button_onclick_value = `window.open('${/*project*/ ctx[0].url}', '_blank', 'noopener,noreferrer')`);
    			attr(div, "class", "pb-detail-modal__view-commands pb-detail-modal__sidebar_element");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			append(div, button);
    			append(button, t);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*project*/ 1 && button_onclick_value !== (button_onclick_value = `window.open('${/*project*/ ctx[0].url}', '_blank', 'noopener,noreferrer')`)) {
    				attr(button, "onclick", button_onclick_value);
    			}
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    		}
    	};
    }

    function create_fragment$5(ctx) {
    	let div6;
    	let div4;
    	let div1;
    	let image;
    	let t0;
    	let div0;
    	let h2;
    	let t1_value = /*project*/ ctx[0].title + "";
    	let t1;
    	let t2;
    	let div2;
    	let t3;
    	let div3;
    	let raw_value = /*project*/ ctx[0].body.summary + "";
    	let t4;
    	let t5;
    	let t6;
    	let div5;
    	let t7;
    	let t8;
    	let t9;
    	let t10;
    	let t11;
    	let t12;
    	let current;

    	image = new Image({
    			props: {
    				sources: /*project*/ ctx[0].logo,
    				class: "pb-detail-modal__title-logo"
    			}
    		});

    	let if_block0 = /*project*/ ctx[0].module_categories.length && create_if_block_9(ctx);
    	let if_block1 = /*project*/ ctx[0].project_images.length > 0 && create_if_block_8(ctx);
    	let if_block2 = /*project*/ ctx[0].body.value && create_if_block_7(ctx);
    	let if_block3 = PACKAGE_MANAGER && create_if_block_6(ctx);
    	let if_block4 = /*project*/ ctx[0].is_compatible && create_if_block_5();
    	let if_block5 = !/*project*/ ctx[0].is_compatible && create_if_block_4$1();
    	let if_block6 = typeof /*project*/ ctx[0].project_usage_total === 'number' && create_if_block_3$2(ctx);
    	let if_block7 = /*project*/ ctx[0].is_covered && create_if_block_2$2();
    	let if_block8 = /*project*/ ctx[0].is_maintained && create_if_block_1$3();
    	let if_block9 = /*project*/ ctx[0].url && create_if_block$3(ctx);

    	return {
    		c() {
    			div6 = element("div");
    			div4 = element("div");
    			div1 = element("div");
    			create_component(image.$$.fragment);
    			t0 = space();
    			div0 = element("div");
    			h2 = element("h2");
    			t1 = text(t1_value);
    			t2 = space();
    			div2 = element("div");
    			if (if_block0) if_block0.c();
    			t3 = space();
    			div3 = element("div");
    			t4 = space();
    			if (if_block1) if_block1.c();
    			t5 = space();
    			if (if_block2) if_block2.c();
    			t6 = space();
    			div5 = element("div");
    			if (if_block3) if_block3.c();
    			t7 = space();
    			if (if_block4) if_block4.c();
    			t8 = space();
    			if (if_block5) if_block5.c();
    			t9 = space();
    			if (if_block6) if_block6.c();
    			t10 = space();
    			if (if_block7) if_block7.c();
    			t11 = space();
    			if (if_block8) if_block8.c();
    			t12 = space();
    			if (if_block9) if_block9.c();
    			attr(h2, "class", "pb-detail-modal__title-label");
    			attr(div0, "class", "pb-detail-modal__title");
    			attr(div1, "class", "pb-detail-modal__header");
    			attr(div2, "class", "pb-detail-modal__details");
    			attr(div3, "class", "pb-detail-modal__description");
    			attr(div3, "id", "summary-wrapper");
    			attr(div4, "class", "pb-detail-modal__main");
    			attr(div5, "class", "pb-detail-modal__sidebar");
    			attr(div6, "class", "pb-detail-modal");
    		},
    		m(target, anchor) {
    			insert(target, div6, anchor);
    			append(div6, div4);
    			append(div4, div1);
    			mount_component(image, div1, null);
    			append(div1, t0);
    			append(div1, div0);
    			append(div0, h2);
    			append(h2, t1);
    			append(div4, t2);
    			append(div4, div2);
    			if (if_block0) if_block0.m(div2, null);
    			append(div4, t3);
    			append(div4, div3);
    			div3.innerHTML = raw_value;
    			append(div4, t4);
    			if (if_block1) if_block1.m(div4, null);
    			append(div4, t5);
    			if (if_block2) if_block2.m(div4, null);
    			append(div6, t6);
    			append(div6, div5);
    			if (if_block3) if_block3.m(div5, null);
    			append(div5, t7);
    			if (if_block4) if_block4.m(div5, null);
    			append(div5, t8);
    			if (if_block5) if_block5.m(div5, null);
    			append(div5, t9);
    			if (if_block6) if_block6.m(div5, null);
    			append(div5, t10);
    			if (if_block7) if_block7.m(div5, null);
    			append(div5, t11);
    			if (if_block8) if_block8.m(div5, null);
    			append(div5, t12);
    			if (if_block9) if_block9.m(div5, null);
    			current = true;
    		},
    		p(ctx, [dirty]) {
    			const image_changes = {};
    			if (dirty & /*project*/ 1) image_changes.sources = /*project*/ ctx[0].logo;
    			image.$set(image_changes);
    			if ((!current || dirty & /*project*/ 1) && t1_value !== (t1_value = /*project*/ ctx[0].title + "")) set_data(t1, t1_value);

    			if (/*project*/ ctx[0].module_categories.length) {
    				if (if_block0) {
    					if_block0.p(ctx, dirty);
    				} else {
    					if_block0 = create_if_block_9(ctx);
    					if_block0.c();
    					if_block0.m(div2, null);
    				}
    			} else if (if_block0) {
    				if_block0.d(1);
    				if_block0 = null;
    			}

    			if ((!current || dirty & /*project*/ 1) && raw_value !== (raw_value = /*project*/ ctx[0].body.summary + "")) div3.innerHTML = raw_value;
    			if (/*project*/ ctx[0].project_images.length > 0) {
    				if (if_block1) {
    					if_block1.p(ctx, dirty);

    					if (dirty & /*project*/ 1) {
    						transition_in(if_block1, 1);
    					}
    				} else {
    					if_block1 = create_if_block_8(ctx);
    					if_block1.c();
    					transition_in(if_block1, 1);
    					if_block1.m(div4, t5);
    				}
    			} else if (if_block1) {
    				group_outros();

    				transition_out(if_block1, 1, 1, () => {
    					if_block1 = null;
    				});

    				check_outros();
    			}

    			if (/*project*/ ctx[0].body.value) {
    				if (if_block2) {
    					if_block2.p(ctx, dirty);
    				} else {
    					if_block2 = create_if_block_7(ctx);
    					if_block2.c();
    					if_block2.m(div4, null);
    				}
    			} else if (if_block2) {
    				if_block2.d(1);
    				if_block2 = null;
    			}

    			if (PACKAGE_MANAGER) if_block3.p(ctx, dirty);

    			if (/*project*/ ctx[0].is_compatible) {
    				if (if_block4) {
    					if_block4.p(ctx, dirty);

    					if (dirty & /*project*/ 1) {
    						transition_in(if_block4, 1);
    					}
    				} else {
    					if_block4 = create_if_block_5();
    					if_block4.c();
    					transition_in(if_block4, 1);
    					if_block4.m(div5, t8);
    				}
    			} else if (if_block4) {
    				group_outros();

    				transition_out(if_block4, 1, 1, () => {
    					if_block4 = null;
    				});

    				check_outros();
    			}

    			if (!/*project*/ ctx[0].is_compatible) {
    				if (if_block5) {
    					if_block5.p(ctx, dirty);
    				} else {
    					if_block5 = create_if_block_4$1();
    					if_block5.c();
    					if_block5.m(div5, t9);
    				}
    			} else if (if_block5) {
    				if_block5.d(1);
    				if_block5 = null;
    			}

    			if (typeof /*project*/ ctx[0].project_usage_total === 'number') {
    				if (if_block6) {
    					if_block6.p(ctx, dirty);

    					if (dirty & /*project*/ 1) {
    						transition_in(if_block6, 1);
    					}
    				} else {
    					if_block6 = create_if_block_3$2(ctx);
    					if_block6.c();
    					transition_in(if_block6, 1);
    					if_block6.m(div5, t10);
    				}
    			} else if (if_block6) {
    				group_outros();

    				transition_out(if_block6, 1, 1, () => {
    					if_block6 = null;
    				});

    				check_outros();
    			}

    			if (/*project*/ ctx[0].is_covered) {
    				if (if_block7) {
    					if_block7.p(ctx, dirty);

    					if (dirty & /*project*/ 1) {
    						transition_in(if_block7, 1);
    					}
    				} else {
    					if_block7 = create_if_block_2$2();
    					if_block7.c();
    					transition_in(if_block7, 1);
    					if_block7.m(div5, t11);
    				}
    			} else if (if_block7) {
    				group_outros();

    				transition_out(if_block7, 1, 1, () => {
    					if_block7 = null;
    				});

    				check_outros();
    			}

    			if (/*project*/ ctx[0].is_maintained) {
    				if (if_block8) {
    					if_block8.p(ctx, dirty);

    					if (dirty & /*project*/ 1) {
    						transition_in(if_block8, 1);
    					}
    				} else {
    					if_block8 = create_if_block_1$3();
    					if_block8.c();
    					transition_in(if_block8, 1);
    					if_block8.m(div5, t12);
    				}
    			} else if (if_block8) {
    				group_outros();

    				transition_out(if_block8, 1, 1, () => {
    					if_block8 = null;
    				});

    				check_outros();
    			}

    			if (/*project*/ ctx[0].url) {
    				if (if_block9) {
    					if_block9.p(ctx, dirty);
    				} else {
    					if_block9 = create_if_block$3(ctx);
    					if_block9.c();
    					if_block9.m(div5, null);
    				}
    			} else if (if_block9) {
    				if_block9.d(1);
    				if_block9 = null;
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(image.$$.fragment, local);
    			transition_in(if_block1);
    			transition_in(if_block3);
    			transition_in(if_block4);
    			transition_in(if_block6);
    			transition_in(if_block7);
    			transition_in(if_block8);
    			current = true;
    		},
    		o(local) {
    			transition_out(image.$$.fragment, local);
    			transition_out(if_block1);
    			transition_out(if_block3);
    			transition_out(if_block4);
    			transition_out(if_block6);
    			transition_out(if_block7);
    			transition_out(if_block8);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div6);
    			destroy_component(image);
    			if (if_block0) if_block0.d();
    			if (if_block1) if_block1.d();
    			if (if_block2) if_block2.d();
    			if (if_block3) if_block3.d();
    			if (if_block4) if_block4.d();
    			if (if_block5) if_block5.d();
    			if (if_block6) if_block6.d();
    			if (if_block7) if_block7.d();
    			if (if_block8) if_block8.d();
    			if (if_block9) if_block9.d();
    		}
    	};
    }

    const func = category => category.name;

    function instance$5($$self, $$props, $$invalidate) {
    	let { project } = $$props;
    	const { Drupal } = window;

    	onMount(() => {
    		const description = document.createElement('div');
    		description.innerHTML = project.body.value ? project.body.value : '';
    		const anchors = description.getElementsByTagName('a');

    		for (let i = 0; i < anchors.length; i++) {
    			anchors[i].setAttribute('target', '_blank');
    			anchors[i].setAttribute('rel', 'noopener noreferrer');
    		}

    		$$invalidate(0, project.body.value = description.innerHTML, project);
    	});

    	$$self.$$set = $$props => {
    		if ('project' in $$props) $$invalidate(0, project = $$props.project);
    	};

    	return [project, Drupal];
    }

    class DetailModal extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$5, create_fragment$5, safe_not_equal, { project: 0 });
    	}
    }

    /* src/Project/Categories.svelte generated by Svelte v3.48.0 */

    function get_each_context$1(ctx, list, i) {
    	const child_ctx = ctx.slice();
    	child_ctx[2] = list[i];
    	child_ctx[4] = i;
    	return child_ctx;
    }

    // (16:2) {#if typeof moduleCategories !== 'undefined' && moduleCategories.length}
    function create_if_block$2(ctx) {
    	let ul;
    	let each_value = /*moduleCategories*/ ctx[0] || [];
    	let each_blocks = [];

    	for (let i = 0; i < each_value.length; i += 1) {
    		each_blocks[i] = create_each_block$1(get_each_context$1(ctx, each_value, i));
    	}

    	return {
    		c() {
    			ul = element("ul");

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].c();
    			}

    			attr(ul, "class", "pb-project-categories__list");
    			attr(ul, "aria-label", "Categories");
    		},
    		m(target, anchor) {
    			insert(target, ul, anchor);

    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].m(ul, null);
    			}
    		},
    		p(ctx, dirty) {
    			if (dirty & /*moduleCategories*/ 1) {
    				each_value = /*moduleCategories*/ ctx[0] || [];
    				let i;

    				for (i = 0; i < each_value.length; i += 1) {
    					const child_ctx = get_each_context$1(ctx, each_value, i);

    					if (each_blocks[i]) {
    						each_blocks[i].p(child_ctx, dirty);
    					} else {
    						each_blocks[i] = create_each_block$1(child_ctx);
    						each_blocks[i].c();
    						each_blocks[i].m(ul, null);
    					}
    				}

    				for (; i < each_blocks.length; i += 1) {
    					each_blocks[i].d(1);
    				}

    				each_blocks.length = each_value.length;
    			}
    		},
    		d(detaching) {
    			if (detaching) detach(ul);
    			destroy_each(each_blocks, detaching);
    		}
    	};
    }

    // (25:10) {:else}
    function create_else_block(ctx) {
    	let t_value = /*category*/ ctx[2].name + "";
    	let t;

    	return {
    		c() {
    			t = text(t_value);
    		},
    		m(target, anchor) {
    			insert(target, t, anchor);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*moduleCategories*/ 1 && t_value !== (t_value = /*category*/ ctx[2].name + "")) set_data(t, t_value);
    		},
    		d(detaching) {
    			if (detaching) detach(t);
    		}
    	};
    }

    // (23:10) {#if index + 1 !== moduleCategories.length}
    function create_if_block_1$2(ctx) {
    	let t0_value = /*category*/ ctx[2].name + "";
    	let t0;
    	let t1;

    	return {
    		c() {
    			t0 = text(t0_value);
    			t1 = text(",");
    		},
    		m(target, anchor) {
    			insert(target, t0, anchor);
    			insert(target, t1, anchor);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*moduleCategories*/ 1 && t0_value !== (t0_value = /*category*/ ctx[2].name + "")) set_data(t0, t0_value);
    		},
    		d(detaching) {
    			if (detaching) detach(t0);
    			if (detaching) detach(t1);
    		}
    	};
    }

    // (18:6) {#each moduleCategories || [] as category, index}
    function create_each_block$1(ctx) {
    	let li;
    	let t;

    	function select_block_type(ctx, dirty) {
    		if (/*index*/ ctx[4] + 1 !== /*moduleCategories*/ ctx[0].length) return create_if_block_1$2;
    		return create_else_block;
    	}

    	let current_block_type = select_block_type(ctx);
    	let if_block = current_block_type(ctx);

    	return {
    		c() {
    			li = element("li");
    			if_block.c();
    			t = space();
    			attr(li, "class", "pb-project-categories__item");
    			toggle_class(li, "pb-project-categories__item--extra", /*category*/ ctx[2].id === 'overflow');
    		},
    		m(target, anchor) {
    			insert(target, li, anchor);
    			if_block.m(li, null);
    			append(li, t);
    		},
    		p(ctx, dirty) {
    			if (current_block_type === (current_block_type = select_block_type(ctx)) && if_block) {
    				if_block.p(ctx, dirty);
    			} else {
    				if_block.d(1);
    				if_block = current_block_type(ctx);

    				if (if_block) {
    					if_block.c();
    					if_block.m(li, t);
    				}
    			}

    			if (dirty & /*moduleCategories*/ 1) {
    				toggle_class(li, "pb-project-categories__item--extra", /*category*/ ctx[2].id === 'overflow');
    			}
    		},
    		d(detaching) {
    			if (detaching) detach(li);
    			if_block.d();
    		}
    	};
    }

    function create_fragment$4(ctx) {
    	let div;
    	let if_block = typeof /*moduleCategories*/ ctx[0] !== 'undefined' && /*moduleCategories*/ ctx[0].length && create_if_block$2(ctx);

    	return {
    		c() {
    			div = element("div");
    			if (if_block) if_block.c();
    			attr(div, "class", "pb-project-categories");
    			attr(div, "data-label", "Categories");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			if (if_block) if_block.m(div, null);
    		},
    		p(ctx, [dirty]) {
    			if (typeof /*moduleCategories*/ ctx[0] !== 'undefined' && /*moduleCategories*/ ctx[0].length) {
    				if (if_block) {
    					if_block.p(ctx, dirty);
    				} else {
    					if_block = create_if_block$2(ctx);
    					if_block.c();
    					if_block.m(div, null);
    				}
    			} else if (if_block) {
    				if_block.d(1);
    				if_block = null;
    			}
    		},
    		i: noop,
    		o: noop,
    		d(detaching) {
    			if (detaching) detach(div);
    			if (if_block) if_block.d();
    		}
    	};
    }

    function instance$4($$self, $$props, $$invalidate) {
    	let { moduleCategories } = $$props;
    	const extraCategories = moduleCategories.splice(3);

    	if (extraCategories.length) {
    		const overflowText = window.Drupal.t('+ @count more', { '@count': extraCategories.length });
    		moduleCategories.push({ id: 'overflow', name: overflowText });
    	}

    	$$self.$$set = $$props => {
    		if ('moduleCategories' in $$props) $$invalidate(0, moduleCategories = $$props.moduleCategories);
    	};

    	return [moduleCategories];
    }

    class Categories extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$4, create_fragment$4, safe_not_equal, { moduleCategories: 0 });
    	}
    }

    /* src/Project/Project.svelte generated by Svelte v3.48.0 */

    function create_default_slot$1(ctx) {
    	let t_value = /*project*/ ctx[0].title + "";
    	let t;

    	return {
    		c() {
    			t = text(t_value);
    		},
    		m(target, anchor) {
    			insert(target, t, anchor);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*project*/ 1 && t_value !== (t_value = /*project*/ ctx[0].title + "")) set_data(t, t_value);
    		},
    		d(detaching) {
    			if (detaching) detach(t);
    		}
    	};
    }

    // (62:4) {#if project.is_covered}
    function create_if_block_3$1(ctx) {
    	let span;
    	let projecticon;
    	let current;
    	projecticon = new ProjectIcon({ props: { type: "status" } });

    	return {
    		c() {
    			span = element("span");
    			create_component(projecticon.$$.fragment);
    			attr(span, "class", "pb-project__status-icon");
    		},
    		m(target, anchor) {
    			insert(target, span, anchor);
    			mount_component(projecticon, span, null);
    			current = true;
    		},
    		i(local) {
    			if (current) return;
    			transition_in(projecticon.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projecticon.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(span);
    			destroy_component(projecticon);
    		}
    	};
    }

    // (67:4) {#if project.is_maintained}
    function create_if_block_2$1(ctx) {
    	let span;
    	let projecticon;
    	let current;
    	projecticon = new ProjectIcon({ props: { type: "maintained" } });

    	return {
    		c() {
    			span = element("span");
    			create_component(projecticon.$$.fragment);
    			attr(span, "class", "pb-project__maintenance-icon");
    		},
    		m(target, anchor) {
    			insert(target, span, anchor);
    			mount_component(projecticon, span, null);
    			current = true;
    		},
    		i(local) {
    			if (current) return;
    			transition_in(projecticon.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projecticon.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(span);
    			destroy_component(projecticon);
    		}
    	};
    }

    // (72:4) {#if toggleView === 'Grid' && typeof project.project_usage_total === 'number' && project.project_usage_total > 0}
    function create_if_block_1$1(ctx) {
    	let div;
    	let span;
    	let t_value = /*Drupal*/ ctx[4].formatPlural(/*project*/ ctx[0].project_usage_total, `${numberFormatter.format(1)} install`, `${numberFormatter.format(/*project*/ ctx[0].project_usage_total)} installs`) + "";
    	let t;

    	return {
    		c() {
    			div = element("div");
    			span = element("span");
    			t = text(t_value);
    			attr(span, "class", "pb-project__install-count");
    			attr(div, "class", "pb-project__install-count-container");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			append(div, span);
    			append(span, t);
    		},
    		p(ctx, dirty) {
    			if (dirty & /*project*/ 1 && t_value !== (t_value = /*Drupal*/ ctx[4].formatPlural(/*project*/ ctx[0].project_usage_total, `${numberFormatter.format(1)} install`, `${numberFormatter.format(/*project*/ ctx[0].project_usage_total)} installs`) + "")) set_data(t, t_value);
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    		}
    	};
    }

    // (83:4) {#if toggleView === 'List' && typeof project.project_usage_total === 'number' && project.project_usage_total > 0}
    function create_if_block$1(ctx) {
    	let div2;
    	let div0;
    	let projecticon;
    	let div0_class_value;
    	let t0;
    	let div1;
    	let t1_value = /*Drupal*/ ctx[4].formatPlural(/*project*/ ctx[0].project_usage_total, `${numberFormatter.format(1)} Active Install`, `${numberFormatter.format(/*project*/ ctx[0].project_usage_total)} Active Installs`) + "";
    	let t1;
    	let current;

    	projecticon = new ProjectIcon({
    			props: {
    				type: "usage",
    				variant: "project-listing"
    			}
    		});

    	return {
    		c() {
    			div2 = element("div");
    			div0 = element("div");
    			create_component(projecticon.$$.fragment);
    			t0 = space();
    			div1 = element("div");
    			t1 = text(t1_value);
    			attr(div0, "class", div0_class_value = "pb-project__image pb-project__image--" + /*displayMode*/ ctx[2]);
    			attr(div1, "class", "pb-project__active-installs-text");
    			attr(div2, "class", "pb-project__project-usage-container");
    		},
    		m(target, anchor) {
    			insert(target, div2, anchor);
    			append(div2, div0);
    			mount_component(projecticon, div0, null);
    			append(div2, t0);
    			append(div2, div1);
    			append(div1, t1);
    			current = true;
    		},
    		p(ctx, dirty) {
    			if (!current || dirty & /*displayMode*/ 4 && div0_class_value !== (div0_class_value = "pb-project__image pb-project__image--" + /*displayMode*/ ctx[2])) {
    				attr(div0, "class", div0_class_value);
    			}

    			if ((!current || dirty & /*project*/ 1) && t1_value !== (t1_value = /*Drupal*/ ctx[4].formatPlural(/*project*/ ctx[0].project_usage_total, `${numberFormatter.format(1)} Active Install`, `${numberFormatter.format(/*project*/ ctx[0].project_usage_total)} Active Installs`) + "")) set_data(t1, t1_value);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(projecticon.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projecticon.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div2);
    			destroy_component(projecticon);
    		}
    	};
    }

    function create_fragment$3(ctx) {
    	let li;
    	let div0;
    	let image;
    	let div0_class_value;
    	let t0;
    	let div2;
    	let h3;
    	let projectbuttonbase;
    	let h3_class_value;
    	let t1;
    	let div1;
    	let raw_value = /*project*/ ctx[0].body.summary + "";
    	let div1_class_value;
    	let t2;
    	let categories;
    	let div2_class_value;
    	let t3;
    	let div3;
    	let t4;
    	let t5;
    	let t6;
    	let t7;
    	let actionbutton;
    	let div3_class_value;
    	let li_class_value;
    	let li_data_project_id_value;
    	let current;
    	let mounted;
    	let dispose;

    	image = new Image({
    			props: {
    				sources: /*project*/ ctx[0].logo,
    				class: "pb-project__logo-image"
    			}
    		});

    	projectbuttonbase = new ProjectButtonBase({
    			props: {
    				id: "" + (/*project*/ ctx[0].project_machine_name + "_title"),
    				class: "pb-project__link",
    				"aria-haspopup": "dialog",
    				click: /*func*/ ctx[8],
    				$$slots: { default: [create_default_slot$1] },
    				$$scope: { ctx }
    			}
    		});

    	categories = new Categories({
    			props: {
    				toggleView: /*toggleView*/ ctx[1],
    				moduleCategories: /*project*/ ctx[0].module_categories
    			}
    		});

    	let if_block0 = /*project*/ ctx[0].is_covered && create_if_block_3$1();
    	let if_block1 = /*project*/ ctx[0].is_maintained && create_if_block_2$1();
    	let if_block2 = /*toggleView*/ ctx[1] === 'Grid' && typeof /*project*/ ctx[0].project_usage_total === 'number' && /*project*/ ctx[0].project_usage_total > 0 && create_if_block_1$1(ctx);
    	let if_block3 = /*toggleView*/ ctx[1] === 'List' && typeof /*project*/ ctx[0].project_usage_total === 'number' && /*project*/ ctx[0].project_usage_total > 0 && create_if_block$1(ctx);
    	actionbutton = new ActionButton({ props: { project: /*project*/ ctx[0] } });

    	return {
    		c() {
    			li = element("li");
    			div0 = element("div");
    			create_component(image.$$.fragment);
    			t0 = space();
    			div2 = element("div");
    			h3 = element("h3");
    			create_component(projectbuttonbase.$$.fragment);
    			t1 = space();
    			div1 = element("div");
    			t2 = space();
    			create_component(categories.$$.fragment);
    			t3 = space();
    			div3 = element("div");
    			if (if_block0) if_block0.c();
    			t4 = space();
    			if (if_block1) if_block1.c();
    			t5 = space();
    			if (if_block2) if_block2.c();
    			t6 = space();
    			if (if_block3) if_block3.c();
    			t7 = space();
    			create_component(actionbutton.$$.fragment);
    			attr(div0, "class", div0_class_value = "pb-project__logo pb-project__logo--" + /*displayMode*/ ctx[2]);
    			attr(h3, "class", h3_class_value = "pb-project__title pb-project__title--" + /*displayMode*/ ctx[2]);
    			attr(div1, "class", div1_class_value = "pb-project__body pb-project__body--" + /*displayMode*/ ctx[2]);
    			attr(div2, "class", div2_class_value = "pb-project__main pb-project__main--" + /*displayMode*/ ctx[2]);
    			attr(div3, "class", div3_class_value = "pb-project__icons pb-project__icons--" + /*displayMode*/ ctx[2]);
    			attr(li, "class", li_class_value = "pb-project pb-project--" + /*displayMode*/ ctx[2]);
    			attr(li, "data-project-id", li_data_project_id_value = /*project*/ ctx[0].id);
    		},
    		m(target, anchor) {
    			insert(target, li, anchor);
    			append(li, div0);
    			mount_component(image, div0, null);
    			append(li, t0);
    			append(li, div2);
    			append(div2, h3);
    			mount_component(projectbuttonbase, h3, null);
    			append(div2, t1);
    			append(div2, div1);
    			div1.innerHTML = raw_value;
    			append(div2, t2);
    			mount_component(categories, div2, null);
    			append(li, t3);
    			append(li, div3);
    			if (if_block0) if_block0.m(div3, null);
    			append(div3, t4);
    			if (if_block1) if_block1.m(div3, null);
    			append(div3, t5);
    			if (if_block2) if_block2.m(div3, null);
    			append(div3, t6);
    			if (if_block3) if_block3.m(div3, null);
    			append(div3, t7);
    			mount_component(actionbutton, div3, null);
    			current = true;

    			if (!mounted) {
    				dispose = listen(h3, "click", /*click_handler*/ ctx[9]);
    				mounted = true;
    			}
    		},
    		p(ctx, [dirty]) {
    			const image_changes = {};
    			if (dirty & /*project*/ 1) image_changes.sources = /*project*/ ctx[0].logo;
    			image.$set(image_changes);

    			if (!current || dirty & /*displayMode*/ 4 && div0_class_value !== (div0_class_value = "pb-project__logo pb-project__logo--" + /*displayMode*/ ctx[2])) {
    				attr(div0, "class", div0_class_value);
    			}

    			const projectbuttonbase_changes = {};
    			if (dirty & /*project*/ 1) projectbuttonbase_changes.id = "" + (/*project*/ ctx[0].project_machine_name + "_title");
    			if (dirty & /*project*/ 1) projectbuttonbase_changes.click = /*func*/ ctx[8];

    			if (dirty & /*$$scope, project*/ 2049) {
    				projectbuttonbase_changes.$$scope = { dirty, ctx };
    			}

    			projectbuttonbase.$set(projectbuttonbase_changes);

    			if (!current || dirty & /*displayMode*/ 4 && h3_class_value !== (h3_class_value = "pb-project__title pb-project__title--" + /*displayMode*/ ctx[2])) {
    				attr(h3, "class", h3_class_value);
    			}

    			if ((!current || dirty & /*project*/ 1) && raw_value !== (raw_value = /*project*/ ctx[0].body.summary + "")) div1.innerHTML = raw_value;
    			if (!current || dirty & /*displayMode*/ 4 && div1_class_value !== (div1_class_value = "pb-project__body pb-project__body--" + /*displayMode*/ ctx[2])) {
    				attr(div1, "class", div1_class_value);
    			}

    			const categories_changes = {};
    			if (dirty & /*toggleView*/ 2) categories_changes.toggleView = /*toggleView*/ ctx[1];
    			if (dirty & /*project*/ 1) categories_changes.moduleCategories = /*project*/ ctx[0].module_categories;
    			categories.$set(categories_changes);

    			if (!current || dirty & /*displayMode*/ 4 && div2_class_value !== (div2_class_value = "pb-project__main pb-project__main--" + /*displayMode*/ ctx[2])) {
    				attr(div2, "class", div2_class_value);
    			}

    			if (/*project*/ ctx[0].is_covered) {
    				if (if_block0) {
    					if (dirty & /*project*/ 1) {
    						transition_in(if_block0, 1);
    					}
    				} else {
    					if_block0 = create_if_block_3$1();
    					if_block0.c();
    					transition_in(if_block0, 1);
    					if_block0.m(div3, t4);
    				}
    			} else if (if_block0) {
    				group_outros();

    				transition_out(if_block0, 1, 1, () => {
    					if_block0 = null;
    				});

    				check_outros();
    			}

    			if (/*project*/ ctx[0].is_maintained) {
    				if (if_block1) {
    					if (dirty & /*project*/ 1) {
    						transition_in(if_block1, 1);
    					}
    				} else {
    					if_block1 = create_if_block_2$1();
    					if_block1.c();
    					transition_in(if_block1, 1);
    					if_block1.m(div3, t5);
    				}
    			} else if (if_block1) {
    				group_outros();

    				transition_out(if_block1, 1, 1, () => {
    					if_block1 = null;
    				});

    				check_outros();
    			}

    			if (/*toggleView*/ ctx[1] === 'Grid' && typeof /*project*/ ctx[0].project_usage_total === 'number' && /*project*/ ctx[0].project_usage_total > 0) {
    				if (if_block2) {
    					if_block2.p(ctx, dirty);
    				} else {
    					if_block2 = create_if_block_1$1(ctx);
    					if_block2.c();
    					if_block2.m(div3, t6);
    				}
    			} else if (if_block2) {
    				if_block2.d(1);
    				if_block2 = null;
    			}

    			if (/*toggleView*/ ctx[1] === 'List' && typeof /*project*/ ctx[0].project_usage_total === 'number' && /*project*/ ctx[0].project_usage_total > 0) {
    				if (if_block3) {
    					if_block3.p(ctx, dirty);

    					if (dirty & /*toggleView, project*/ 3) {
    						transition_in(if_block3, 1);
    					}
    				} else {
    					if_block3 = create_if_block$1(ctx);
    					if_block3.c();
    					transition_in(if_block3, 1);
    					if_block3.m(div3, t7);
    				}
    			} else if (if_block3) {
    				group_outros();

    				transition_out(if_block3, 1, 1, () => {
    					if_block3 = null;
    				});

    				check_outros();
    			}

    			const actionbutton_changes = {};
    			if (dirty & /*project*/ 1) actionbutton_changes.project = /*project*/ ctx[0];
    			actionbutton.$set(actionbutton_changes);

    			if (!current || dirty & /*displayMode*/ 4 && div3_class_value !== (div3_class_value = "pb-project__icons pb-project__icons--" + /*displayMode*/ ctx[2])) {
    				attr(div3, "class", div3_class_value);
    			}

    			if (!current || dirty & /*displayMode*/ 4 && li_class_value !== (li_class_value = "pb-project pb-project--" + /*displayMode*/ ctx[2])) {
    				attr(li, "class", li_class_value);
    			}

    			if (!current || dirty & /*project*/ 1 && li_data_project_id_value !== (li_data_project_id_value = /*project*/ ctx[0].id)) {
    				attr(li, "data-project-id", li_data_project_id_value);
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(image.$$.fragment, local);
    			transition_in(projectbuttonbase.$$.fragment, local);
    			transition_in(categories.$$.fragment, local);
    			transition_in(if_block0);
    			transition_in(if_block1);
    			transition_in(if_block3);
    			transition_in(actionbutton.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(image.$$.fragment, local);
    			transition_out(projectbuttonbase.$$.fragment, local);
    			transition_out(categories.$$.fragment, local);
    			transition_out(if_block0);
    			transition_out(if_block1);
    			transition_out(if_block3);
    			transition_out(actionbutton.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(li);
    			destroy_component(image);
    			destroy_component(projectbuttonbase);
    			destroy_component(categories);
    			if (if_block0) if_block0.d();
    			if (if_block1) if_block1.d();
    			if (if_block2) if_block2.d();
    			if (if_block3) if_block3.d();
    			destroy_component(actionbutton);
    			mounted = false;
    			dispose();
    		}
    	};
    }

    function instance$3($$self, $$props, $$invalidate) {
    	let isDesktop;
    	let displayMode;
    	let $focusedElement;
    	let { project } = $$props;
    	let { toggleView } = $$props;
    	const { Drupal } = window;
    	const focusedElement = getContext('focusedElement');
    	component_subscribe($$self, focusedElement, value => $$invalidate(3, $focusedElement = value));
    	const mediaQueryValues = getContext('mediaQueryValues');
    	let mqMatches;

    	mediaQueryValues.subscribe(mqlMap => {
    		$$invalidate(6, mqMatches = mqlMap.get('(min-width: 1200px)'));
    	});

    	const func = () => {
    		const modalDialog = document.createElement('div');
    		(() => new DetailModal({ target: modalDialog, props: { project } }))();
    		openPopup(modalDialog, project);
    	};

    	const click_handler = () => {
    		set_store_value(focusedElement, $focusedElement = `${project.project_machine_name}_title`, $focusedElement);
    	};

    	$$self.$$set = $$props => {
    		if ('project' in $$props) $$invalidate(0, project = $$props.project);
    		if ('toggleView' in $$props) $$invalidate(1, toggleView = $$props.toggleView);
    	};

    	$$self.$$.update = () => {
    		if ($$self.$$.dirty & /*mqMatches*/ 64) {
    			$$invalidate(7, isDesktop = mqMatches);
    		}

    		if ($$self.$$.dirty & /*isDesktop, toggleView*/ 130) {
    			$$invalidate(2, displayMode = isDesktop ? toggleView.toLowerCase() : 'list');
    		}
    	};

    	return [
    		project,
    		toggleView,
    		displayMode,
    		$focusedElement,
    		Drupal,
    		focusedElement,
    		mqMatches,
    		isDesktop,
    		func,
    		click_handler
    	];
    }

    class Project extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$3, create_fragment$3, safe_not_equal, { project: 0, toggleView: 1 });
    	}
    }

    /* src/MediaQuery.svelte generated by Svelte v3.48.0 */
    const get_default_slot_changes = dirty => ({ matches: dirty & /*matches*/ 1 });
    const get_default_slot_context = ctx => ({ matches: /*matches*/ ctx[0] });

    function create_fragment$2(ctx) {
    	let current;
    	const default_slot_template = /*#slots*/ ctx[5].default;
    	const default_slot = create_slot(default_slot_template, ctx, /*$$scope*/ ctx[4], get_default_slot_context);

    	return {
    		c() {
    			if (default_slot) default_slot.c();
    		},
    		m(target, anchor) {
    			if (default_slot) {
    				default_slot.m(target, anchor);
    			}

    			current = true;
    		},
    		p(ctx, [dirty]) {
    			if (default_slot) {
    				if (default_slot.p && (!current || dirty & /*$$scope, matches*/ 17)) {
    					update_slot_base(
    						default_slot,
    						default_slot_template,
    						ctx,
    						/*$$scope*/ ctx[4],
    						!current
    						? get_all_dirty_from_scope(/*$$scope*/ ctx[4])
    						: get_slot_changes(default_slot_template, /*$$scope*/ ctx[4], dirty, get_default_slot_changes),
    						get_default_slot_context
    					);
    				}
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(default_slot, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(default_slot, local);
    			current = false;
    		},
    		d(detaching) {
    			if (default_slot) default_slot.d(detaching);
    		}
    	};
    }

    function instance$2($$self, $$props, $$invalidate) {
    	let $mediaQueryValues;
    	let { $$slots: slots = {}, $$scope } = $$props;
    	let { query } = $$props;
    	let mql;
    	let mqlListener;
    	let wasMounted = false;
    	let matches = false;
    	const mediaQueryValues = getContext('mediaQueryValues');
    	component_subscribe($$self, mediaQueryValues, value => $$invalidate(8, $mediaQueryValues = value));

    	// eslint-disable-next-line no-shadow
    	function addNewListener(query) {
    		mql = window.matchMedia(query);

    		mqlListener = v => {
    			$$invalidate(0, matches = v.matches);

    			// Update store values
    			const currentMqs = $mediaQueryValues;

    			currentMqs.set(query, matches);
    			set_store_value(mediaQueryValues, $mediaQueryValues = currentMqs, $mediaQueryValues);
    		};

    		mql.addEventListener('change', mqlListener);
    		$$invalidate(0, matches = mql.matches);

    		// Set store values on page load
    		const mqs = $mediaQueryValues;

    		mqs.set(query, matches);
    		set_store_value(mediaQueryValues, $mediaQueryValues = mqs, $mediaQueryValues);
    	}

    	function removeActiveListener() {
    		if (mql && mqlListener) {
    			mql.removeListener(mqlListener);
    		}
    	}

    	onMount(() => {
    		$$invalidate(3, wasMounted = true);

    		return () => {
    			removeActiveListener();
    		};
    	});

    	$$self.$$set = $$props => {
    		if ('query' in $$props) $$invalidate(2, query = $$props.query);
    		if ('$$scope' in $$props) $$invalidate(4, $$scope = $$props.$$scope);
    	};

    	$$self.$$.update = () => {
    		if ($$self.$$.dirty & /*wasMounted, query*/ 12) {
    			{
    				if (wasMounted) {
    					removeActiveListener();
    					addNewListener(query);
    				}
    			}
    		}
    	};

    	return [matches, mediaQueryValues, query, wasMounted, $$scope, slots];
    }

    class MediaQuery extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$2, create_fragment$2, safe_not_equal, { query: 2 });
    	}
    }

    // This is the single source of truth for all projects that have been loaded from
    // the backend. It is keyed by fully qualified project ID, and shared by all
    // QueryManager instances.
    const cache =  writable({});

    function getFromCache (projects) {
      const cacheData = get_store_value(cache);
      return projects
        .map(id => cacheData[id])
        .filter(item => typeof item === 'object');
    }

    function updateProjectsInCache (projects) {
      // Use `.update()` so that all subscribers (i.e., individual QueryManager
      // instances) will be notified and receive the latest project data.
      cache.update((cacheData) => {
        projects.forEach((project) => {
          cacheData[project.id] = project;
        });
        return cacheData;
      });
    }

    // Allow cached projects to be updated via AJAX.
    Drupal.AjaxCommands.prototype.refresh_projects = (ajax, { projects }) => {
      updateProjectsInCache(projects);
    };

    /**
     * Handles fetching and temporarily caching project data from the backend.
     *
     * This implements a volatile, centralized caching mechanism, ensuring that
     * all instances of the Project Browser on a single page share a consistent
     * source of truth for project data.
     *
     * The cache lives in memory and is reset upon page reload.
     */
    class QueryManager {
      constructor (paginated) {
        // If pagination is disabled, then the number of results returned from the
        // first page is, effectively, the total number of results.
        this.paginated = paginated;

        // A list of project IDs that were returned by the last query. These are
        // only the project IDs; the most current data for each of them is stored
        // in the static cache.
        this.list = [];
        // The subscribers that are listening for changes in the projects.
        this.subscribers = [];
        // The total (i.e., not paginated) number of results returned by the most
        // recent query.
        this.count = 0;

        // Whenever the cache changes, we want to notify our subscribers about any
        // changes to the projects we have most recently loaded.
        cache.subscribe(() => {
          const projects = getFromCache(this.list);

          this.subscribers.forEach((callback) => {
            callback(projects);
          });
        });

        this.lastQueryParams = null;
      }

      subscribe (callback) {
        const index = this.subscribers.length;
        this.subscribers.push(callback);

        // The store contract requires us to immediately call the new subscriber.
        callback(getFromCache(this.list));

        // The store contract requires us to return an unsubscribe function.
        return () => {
          this.subscribers.splice(index, 1);
        };
      }

      /**
       * Fetch projects from the backend and store them in memory.
       *
       * @param {Object} filters - The filters to apply in the request.
       * @param {Number} page - The current page number.
       * @param {Number} pageSize - Number of items per page.
       * @param {String} sort - Sorting method.
       * @param {String} source - Data source.
       * @return {Promise<Object>} - The list of project objects.
       */
      async load(filters, page, pageSize, sort, source) {
        // Encode the current filter values as URL parameters.
        const searchParams = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
          if (typeof value === 'boolean') {
            value = Number(value).toString();
          }
          searchParams.set(key, value);
        });
        searchParams.set('page', page);
        searchParams.set('limit', pageSize);
        searchParams.set('sort', sort);
        searchParams.set('source', source);

        const queryString = searchParams.toString();

        if (this.lastQueryParams === queryString) {
          return;
        }
        // We're going to query the backend, so reinitialize our internal state.
        this.list = [];
        this.count = 0;
        this.lastQueryParams = queryString;

        const res = await fetch(
          `${BASE_URL}project-browser/data/project?${queryString}`,
        );
        if (!res.ok) {
          return;
        }

        const { error, list: fetchedList, totalResults } = await res.json();
        if (error && error.length) {
          new Drupal.Message().add(error, { type: 'error' });
        }

        fetchedList.forEach((project) => {
          this.list.push(project.id);
        });
        this.count = this.paginated ? totalResults : fetchedList.length;

        updateProjectsInCache(fetchedList);
      }
    }

    /* src/ProjectBrowser.svelte generated by Svelte v3.48.0 */

    const { Map: Map_1 } = globals;

    function get_each_context(ctx, list, i) {
    	const child_ctx = ctx.slice();
    	child_ctx[48] = list[i];
    	return child_ctx;
    }

    // (265:4) {#each rows as row (row.id)}
    function create_each_block(key_1, ctx) {
    	let first;
    	let project;
    	let current;

    	project = new Project({
    			props: {
    				toggleView: /*toggleView*/ ctx[3],
    				project: /*row*/ ctx[48]
    			}
    		});

    	return {
    		key: key_1,
    		first: null,
    		c() {
    			first = empty();
    			create_component(project.$$.fragment);
    			this.first = first;
    		},
    		m(target, anchor) {
    			insert(target, first, anchor);
    			mount_component(project, target, anchor);
    			current = true;
    		},
    		p(new_ctx, dirty) {
    			ctx = new_ctx;
    			const project_changes = {};
    			if (dirty[0] & /*toggleView*/ 8) project_changes.toggleView = /*toggleView*/ ctx[3];
    			if (dirty[0] & /*rows*/ 32) project_changes.project = /*row*/ ctx[48];
    			project.$set(project_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(project.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(project.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(first);
    			destroy_component(project, detaching);
    		}
    	};
    }

    // (202:2) <ProjectGrid {toggleView} {loading} {rows} {pageIndex} {$pageSize} let:rows>
    function create_default_slot_1(ctx) {
    	let each_blocks = [];
    	let each_1_lookup = new Map_1();
    	let each_1_anchor;
    	let current;
    	let each_value = /*rows*/ ctx[5];
    	const get_key = ctx => /*row*/ ctx[48].id;

    	for (let i = 0; i < each_value.length; i += 1) {
    		let child_ctx = get_each_context(ctx, each_value, i);
    		let key = get_key(child_ctx);
    		each_1_lookup.set(key, each_blocks[i] = create_each_block(key, child_ctx));
    	}

    	return {
    		c() {
    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].c();
    			}

    			each_1_anchor = empty();
    		},
    		m(target, anchor) {
    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].m(target, anchor);
    			}

    			insert(target, each_1_anchor, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			if (dirty[0] & /*toggleView, rows*/ 40) {
    				each_value = /*rows*/ ctx[5];
    				group_outros();
    				each_blocks = update_keyed_each(each_blocks, dirty, get_key, 1, ctx, each_value, each_1_lookup, each_1_anchor.parentNode, outro_and_destroy_block, create_each_block, each_1_anchor, get_each_context);
    				check_outros();
    			}
    		},
    		i(local) {
    			if (current) return;

    			for (let i = 0; i < each_value.length; i += 1) {
    				transition_in(each_blocks[i]);
    			}

    			current = true;
    		},
    		o(local) {
    			for (let i = 0; i < each_blocks.length; i += 1) {
    				transition_out(each_blocks[i]);
    			}

    			current = false;
    		},
    		d(detaching) {
    			for (let i = 0; i < each_blocks.length; i += 1) {
    				each_blocks[i].d(detaching);
    			}

    			if (detaching) detach(each_1_anchor);
    		}
    	};
    }

    // (204:6) {#if numberOfFilters > 0 || numberOfSorts > 1}
    function create_if_block_4(ctx) {
    	let search;
    	let current;

    	search = new Search({
    			props: {
    				refreshLiveRegion: /*refreshLiveRegion*/ ctx[25],
    				filterDefinitions: /*filterDefinitions*/ ctx[7],
    				sorts: /*sorts*/ ctx[8]
    			}
    		});

    	search.$on("sort", /*onSort*/ ctx[22]);
    	search.$on("FilterChange", /*onFilterChange*/ ctx[23]);

    	return {
    		c() {
    			create_component(search.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(search, target, anchor);
    			current = true;
    		},
    		p: noop,
    		i(local) {
    			if (current) return;
    			transition_in(search.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(search.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(search, detaching);
    		}
    	};
    }

    // (216:10) {#if rowsCount}
    function create_if_block_3(ctx) {
    	let t_value = /*Drupal*/ ctx[6].formatPlural(/*rowsCount*/ ctx[1], `${numberFormatter.format(1)} Result`, `${numberFormatter.format(/*rowsCount*/ ctx[1])} Results`) + "";
    	let t;

    	return {
    		c() {
    			t = text(t_value);
    		},
    		m(target, anchor) {
    			insert(target, t, anchor);
    		},
    		p(ctx, dirty) {
    			if (dirty[0] & /*rowsCount*/ 2 && t_value !== (t_value = /*Drupal*/ ctx[6].formatPlural(/*rowsCount*/ ctx[1], `${numberFormatter.format(1)} Result`, `${numberFormatter.format(/*rowsCount*/ ctx[1])} Results`) + "")) set_data(t, t_value);
    		},
    		d(detaching) {
    			if (detaching) detach(t);
    		}
    	};
    }

    // (225:8) {#if matches}
    function create_if_block_2(ctx) {
    	let div;
    	let button0;
    	let img0;
    	let img0_src_value;
    	let t0;
    	let t1_value = window.Drupal.t('List') + "";
    	let t1;
    	let button0_aria_pressed_value;
    	let t2;
    	let button1;
    	let img1;
    	let img1_src_value;
    	let t3;
    	let t4_value = window.Drupal.t('Grid') + "";
    	let t4;
    	let button1_aria_pressed_value;
    	let mounted;
    	let dispose;

    	return {
    		c() {
    			div = element("div");
    			button0 = element("button");
    			img0 = element("img");
    			t0 = space();
    			t1 = text(t1_value);
    			t2 = space();
    			button1 = element("button");
    			img1 = element("img");
    			t3 = space();
    			t4 = text(t4_value);
    			attr(img0, "class", "pb-display__button-icon project-browser__list-icon");
    			if (!src_url_equal(img0.src, img0_src_value = "" + (FULL_MODULE_PATH + "/images/list.svg"))) attr(img0, "src", img0_src_value);
    			attr(img0, "alt", "");
    			attr(button0, "class", "pb-display__button pb-display__button--first");
    			button0.value = "List";
    			attr(button0, "aria-pressed", button0_aria_pressed_value = /*toggleView*/ ctx[3] === 'List');
    			toggle_class(button0, "pb-display__button--selected", /*toggleView*/ ctx[3] === 'List');
    			attr(img1, "class", "pb-display__button-icon project-browser__grid-icon");
    			if (!src_url_equal(img1.src, img1_src_value = "" + (FULL_MODULE_PATH + "/images/grid-fill.svg"))) attr(img1, "src", img1_src_value);
    			attr(img1, "alt", "");
    			attr(button1, "class", "pb-display__button pb-display__button--last");
    			button1.value = "Grid";
    			attr(button1, "aria-pressed", button1_aria_pressed_value = /*toggleView*/ ctx[3] === 'Grid');
    			toggle_class(button1, "pb-display__button--selected", /*toggleView*/ ctx[3] === 'Grid');
    			attr(div, "class", "pb-display");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			append(div, button0);
    			append(button0, img0);
    			append(button0, t0);
    			append(button0, t1);
    			append(div, t2);
    			append(div, button1);
    			append(button1, img1);
    			append(button1, t3);
    			append(button1, t4);

    			if (!mounted) {
    				dispose = [
    					listen(button0, "click", /*click_handler*/ ctx[27]),
    					listen(button1, "click", /*click_handler_1*/ ctx[28])
    				];

    				mounted = true;
    			}
    		},
    		p(ctx, dirty) {
    			if (dirty[0] & /*toggleView*/ 8 && button0_aria_pressed_value !== (button0_aria_pressed_value = /*toggleView*/ ctx[3] === 'List')) {
    				attr(button0, "aria-pressed", button0_aria_pressed_value);
    			}

    			if (dirty[0] & /*toggleView*/ 8) {
    				toggle_class(button0, "pb-display__button--selected", /*toggleView*/ ctx[3] === 'List');
    			}

    			if (dirty[0] & /*toggleView*/ 8 && button1_aria_pressed_value !== (button1_aria_pressed_value = /*toggleView*/ ctx[3] === 'Grid')) {
    				attr(button1, "aria-pressed", button1_aria_pressed_value);
    			}

    			if (dirty[0] & /*toggleView*/ 8) {
    				toggle_class(button1, "pb-display__button--selected", /*toggleView*/ ctx[3] === 'Grid');
    			}
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    			mounted = false;
    			run_all(dispose);
    		}
    	};
    }

    // (203:4) 
    function create_head_slot(ctx) {
    	let div2;
    	let t0;
    	let div1;
    	let div0;
    	let t1;
    	let current;
    	let if_block0 = (/*numberOfFilters*/ ctx[11] > 0 || /*numberOfSorts*/ ctx[12] > 1) && create_if_block_4(ctx);
    	let if_block1 = /*rowsCount*/ ctx[1] && create_if_block_3(ctx);
    	let if_block2 = /*matches*/ ctx[47] && create_if_block_2(ctx);

    	return {
    		c() {
    			div2 = element("div");
    			if (if_block0) if_block0.c();
    			t0 = space();
    			div1 = element("div");
    			div0 = element("div");
    			if (if_block1) if_block1.c();
    			t1 = space();
    			if (if_block2) if_block2.c();
    			attr(div0, "class", "pb-search-results");
    			attr(div1, "class", "pb-layout__header");
    			attr(div2, "slot", "head");
    		},
    		m(target, anchor) {
    			insert(target, div2, anchor);
    			if (if_block0) if_block0.m(div2, null);
    			append(div2, t0);
    			append(div2, div1);
    			append(div1, div0);
    			if (if_block1) if_block1.m(div0, null);
    			append(div1, t1);
    			if (if_block2) if_block2.m(div1, null);
    			current = true;
    		},
    		p(ctx, dirty) {
    			if (/*numberOfFilters*/ ctx[11] > 0 || /*numberOfSorts*/ ctx[12] > 1) if_block0.p(ctx, dirty);

    			if (/*rowsCount*/ ctx[1]) {
    				if (if_block1) {
    					if_block1.p(ctx, dirty);
    				} else {
    					if_block1 = create_if_block_3(ctx);
    					if_block1.c();
    					if_block1.m(div0, null);
    				}
    			} else if (if_block1) {
    				if_block1.d(1);
    				if_block1 = null;
    			}

    			if (/*matches*/ ctx[47]) {
    				if (if_block2) {
    					if_block2.p(ctx, dirty);
    				} else {
    					if_block2 = create_if_block_2(ctx);
    					if_block2.c();
    					if_block2.m(div1, null);
    				}
    			} else if (if_block2) {
    				if_block2.d(1);
    				if_block2 = null;
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(if_block0);
    			current = true;
    		},
    		o(local) {
    			transition_out(if_block0);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div2);
    			if (if_block0) if_block0.d();
    			if (if_block1) if_block1.d();
    			if (if_block2) if_block2.d();
    		}
    	};
    }

    // (269:6) {#if paginate}
    function create_if_block_1(ctx) {
    	let pagination;
    	let current;

    	pagination = new Pagination({
    			props: {
    				options: /*pageSizes*/ ctx[10],
    				page: /*$page*/ ctx[0],
    				count: /*rowsCount*/ ctx[1]
    			}
    		});

    	pagination.$on("pageChange", /*onPageChange*/ ctx[20]);
    	pagination.$on("pageSizeChange", /*onPageSizeChange*/ ctx[21]);

    	return {
    		c() {
    			create_component(pagination.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(pagination, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const pagination_changes = {};
    			if (dirty[0] & /*$page*/ 1) pagination_changes.page = /*$page*/ ctx[0];
    			if (dirty[0] & /*rowsCount*/ 2) pagination_changes.count = /*rowsCount*/ ctx[1];
    			pagination.$set(pagination_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(pagination.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(pagination.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(pagination, detaching);
    		}
    	};
    }

    // (268:4) 
    function create_bottom_slot(ctx) {
    	let div;
    	let current;
    	let if_block = /*paginate*/ ctx[9] && create_if_block_1(ctx);

    	return {
    		c() {
    			div = element("div");
    			if (if_block) if_block.c();
    			attr(div, "slot", "bottom");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			if (if_block) if_block.m(div, null);
    			current = true;
    		},
    		p(ctx, dirty) {
    			if (/*paginate*/ ctx[9]) if_block.p(ctx, dirty);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(if_block);
    			current = true;
    		},
    		o(local) {
    			transition_out(if_block);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    			if (if_block) if_block.d();
    		}
    	};
    }

    // (280:2) {#if loading}
    function create_if_block(ctx) {
    	let div;
    	let loading_1;
    	let current;
    	loading_1 = new Loading({});

    	return {
    		c() {
    			div = element("div");
    			create_component(loading_1.$$.fragment);
    			attr(div, "class", "pb-projects__loading-overlay");
    		},
    		m(target, anchor) {
    			insert(target, div, anchor);
    			mount_component(loading_1, div, null);
    			current = true;
    		},
    		i(local) {
    			if (current) return;
    			transition_in(loading_1.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(loading_1.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			if (detaching) detach(div);
    			destroy_component(loading_1);
    		}
    	};
    }

    // (201:0) <MediaQuery query="(min-width: 1200px)" let:matches>
    function create_default_slot(ctx) {
    	let projectgrid;
    	let t;
    	let if_block_anchor;
    	let current;

    	projectgrid = new ProjectGrid({
    			props: {
    				toggleView: /*toggleView*/ ctx[3],
    				loading: /*loading*/ ctx[2],
    				rows: /*rows*/ ctx[5],
    				pageIndex,
    				$pageSize: /*$pageSize*/ ctx[4],
    				$$slots: {
    					bottom: [
    						create_bottom_slot,
    						({ rows }) => ({ 5: rows }),
    						({ rows }) => [rows ? 32 : 0]
    					],
    					head: [
    						create_head_slot,
    						({ rows }) => ({ 5: rows }),
    						({ rows }) => [rows ? 32 : 0]
    					],
    					default: [
    						create_default_slot_1,
    						({ rows }) => ({ 5: rows }),
    						({ rows }) => [rows ? 32 : 0]
    					]
    				},
    				$$scope: { ctx }
    			}
    		});

    	let if_block = /*loading*/ ctx[2] && create_if_block();

    	return {
    		c() {
    			create_component(projectgrid.$$.fragment);
    			t = space();
    			if (if_block) if_block.c();
    			if_block_anchor = empty();
    		},
    		m(target, anchor) {
    			mount_component(projectgrid, target, anchor);
    			insert(target, t, anchor);
    			if (if_block) if_block.m(target, anchor);
    			insert(target, if_block_anchor, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const projectgrid_changes = {};
    			if (dirty[0] & /*toggleView*/ 8) projectgrid_changes.toggleView = /*toggleView*/ ctx[3];
    			if (dirty[0] & /*loading*/ 4) projectgrid_changes.loading = /*loading*/ ctx[2];
    			if (dirty[0] & /*rows*/ 32) projectgrid_changes.rows = /*rows*/ ctx[5];
    			if (dirty[0] & /*$pageSize*/ 16) projectgrid_changes.$pageSize = /*$pageSize*/ ctx[4];

    			if (dirty[0] & /*$page, rowsCount, toggleView, rows*/ 43 | dirty[1] & /*$$scope, matches*/ 1114112) {
    				projectgrid_changes.$$scope = { dirty, ctx };
    			}

    			projectgrid.$set(projectgrid_changes);

    			if (/*loading*/ ctx[2]) {
    				if (if_block) {
    					if (dirty[0] & /*loading*/ 4) {
    						transition_in(if_block, 1);
    					}
    				} else {
    					if_block = create_if_block();
    					if_block.c();
    					transition_in(if_block, 1);
    					if_block.m(if_block_anchor.parentNode, if_block_anchor);
    				}
    			} else if (if_block) {
    				group_outros();

    				transition_out(if_block, 1, 1, () => {
    					if_block = null;
    				});

    				check_outros();
    			}
    		},
    		i(local) {
    			if (current) return;
    			transition_in(projectgrid.$$.fragment, local);
    			transition_in(if_block);
    			current = true;
    		},
    		o(local) {
    			transition_out(projectgrid.$$.fragment, local);
    			transition_out(if_block);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(projectgrid, detaching);
    			if (detaching) detach(t);
    			if (if_block) if_block.d(detaching);
    			if (detaching) detach(if_block_anchor);
    		}
    	};
    }

    function create_fragment$1(ctx) {
    	let mediaquery;
    	let current;

    	mediaquery = new MediaQuery({
    			props: {
    				query: "(min-width: 1200px)",
    				$$slots: {
    					default: [
    						create_default_slot,
    						({ matches }) => ({ 47: matches }),
    						({ matches }) => [0, matches ? 65536 : 0]
    					]
    				},
    				$$scope: { ctx }
    			}
    		});

    	return {
    		c() {
    			create_component(mediaquery.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(mediaquery, target, anchor);
    			current = true;
    		},
    		p(ctx, dirty) {
    			const mediaquery_changes = {};

    			if (dirty[0] & /*loading, toggleView, rows, $pageSize, $page, rowsCount*/ 63 | dirty[1] & /*$$scope, matches*/ 1114112) {
    				mediaquery_changes.$$scope = { dirty, ctx };
    			}

    			mediaquery.$set(mediaquery_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(mediaquery.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(mediaquery.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(mediaquery, detaching);
    		}
    	};
    }

    const pageIndex = 0; // first row

    function instance$1($$self, $$props, $$invalidate) {
    	let $previousPage;
    	let $page;
    	let $sort;
    	let $filters;
    	let $focusedElement;
    	let $installList;
    	let $pageSize;
    	let $currentPage;
    	component_subscribe($$self, installList, $$value => $$invalidate(35, $installList = $$value));
    	const { Drupal, drupalSettings } = window;
    	const { announce } = Drupal;
    	let { id } = $$props;
    	const { filters: filterDefinitions, source, name, sorts, sortBy, paginate, pageSizes } = drupalSettings.project_browser.instances[id];
    	const queryManager = new QueryManager(paginate);
    	const numberOfFilters = Object.keys(filterDefinitions).length;
    	const numberOfSorts = Object.keys(sorts).length;
    	const filters = writable({});
    	component_subscribe($$self, filters, value => $$invalidate(33, $filters = value));

    	Object.entries(filterDefinitions).forEach(([key, definition]) => {
    		set_store_value(filters, $filters[key] = definition.value, $filters);
    	});

    	setContext('filters', filters);
    	const sort = writable(sortBy);
    	component_subscribe($$self, sort, value => $$invalidate(32, $sort = value));
    	setContext('sort', sort);
    	const page = writable(0);
    	component_subscribe($$self, page, value => $$invalidate(0, $page = value));
    	const pageSize = writable(pageSizes[0]);
    	component_subscribe($$self, pageSize, value => $$invalidate(4, $pageSize = value));
    	setContext('pageSize', pageSize);
    	const focusedElement = writable('');
    	component_subscribe($$self, focusedElement, value => $$invalidate(34, $focusedElement = value));
    	setContext('focusedElement', focusedElement);
    	const mediaQueryValues = writable(new Map());
    	setContext('mediaQueryValues', mediaQueryValues);
    	let rowsCount = 0;
    	let rows = [];
    	const preferredView = writable('Grid');

    	// Set up a callback function that will be called whenever project data
    	// is refreshed or loaded from the backend.
    	queryManager.subscribe(projects => {
    		// `rows` is reactive, so just update it to trigger a re-render.
    		$$invalidate(5, rows = projects);
    	});

    	let loading = true;
    	let isFirstLoad = true;
    	let toggleView = 'Grid';

    	preferredView.subscribe(value => {
    		$$invalidate(3, toggleView = value);
    	});

    	const [currentPage, previousPage] = withPrevious(0);
    	component_subscribe($$self, currentPage, value => $$invalidate(36, $currentPage = value));
    	component_subscribe($$self, previousPage, value => $$invalidate(31, $previousPage = value));
    	let element = '';

    	focusedElement.subscribe(value => {
    		element = value;
    	});

    	/**
     * Load data from QueryManager.
     *
     * @return {Promise<void>}
     *   Empty promise that resolves on content load.*
     */
    	async function load() {
    		$$invalidate(2, loading = true);
    		await queryManager.load($filters, $page, $pageSize, $sort, source);
    		$$invalidate(1, rowsCount = queryManager.count);
    		$$invalidate(2, loading = false);

    		if (!isFirstLoad) {
    			const instanceElement = document.querySelector(`[data-project-browser-instance-id="${id}"]`);

    			if (instanceElement) {
    				instanceElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    			}
    		}

    		isFirstLoad = false;
    	}

    	/**
     * Load remote data when the Svelte component is mounted.
     */
    	onMount(async () => {
    		if (MAX_SELECTIONS === 1) {
    			set_store_value(installList, $installList = [], $installList);
    		}

    		await load();
    		const focus = element ? document.getElementById(element) : false;

    		if (focus) {
    			focus.focus();
    			set_store_value(focusedElement, $focusedElement = '', $focusedElement);
    		}
    	});

    	function onPageChange(event) {
    		const activePage = document.querySelector(`[aria-label="Page ${$page + 1}"]`);

    		if (activePage) {
    			activePage.focus();
    		}

    		set_store_value(page, $page = event.detail.page, $page);
    		load();
    	}

    	function onPageSizeChange() {
    		set_store_value(page, $page = 0, $page);
    		load();
    	}

    	async function onSort(event) {
    		set_store_value(sort, $sort = event.detail.sort, $sort);
    		set_store_value(page, $page = 0, $page);
    		await load();
    	}

    	async function onFilterChange(event) {
    		set_store_value(page, $page = 0, $page);
    		set_store_value(filters, $filters = event.detail.filters, $filters);
    		await load();
    	}

    	async function onToggle(val) {
    		if (val !== toggleView) $$invalidate(3, toggleView = val);
    		preferredView.set(val);
    	}

    	/**
     * Refreshes the live region after a filter or search completes.
     */
    	const refreshLiveRegion = () => {
    		if (rowsCount) {
    			// Set announce() to an empty string. This ensures the result count will
    			// be announced after filtering even if the count is the same.
    			announce('');

    			// The announcement is delayed by 210 milliseconds, a wait that is
    			// slightly longer than the 200 millisecond debounce() built into
    			// announce(). This ensures that the above call to reset the aria live
    			// region to an empty string actually takes place instead of being
    			// debounced.
    			setTimeout(
    				() => {
    					announce(window.Drupal.t('@count Results for @source, Sorted by @sortText', {
    						'@count': numberFormatter.format(rowsCount),
    						'@sortText': sorts[$sort],
    						'@source': name
    					}));
    				},
    				210
    			);
    		}
    	};

    	document.onmouseover = function setInnerDocClickTrue() {
    		window.innerDocClick = true;
    	};

    	document.onmouseleave = function setInnerDocClickFalse() {
    		window.innerDocClick = false;
    	};

    	// Handles back button functionality to go back to the previous page the user was on before.
    	window.addEventListener('popstate', () => {
    		// Confirm the popstate event was a back button action by checking that
    		// the user clicked out of the document.
    		if (!window.innerDocClick) {
    			set_store_value(page, $page = $previousPage, $page);
    			load();
    		}
    	});

    	window.onload = { onFilterChange };

    	// Removes initial loader if it exists.
    	const initialLoader = document.getElementById('initial-loader');

    	if (initialLoader) {
    		initialLoader.remove();
    	}

    	const click_handler = e => {
    		$$invalidate(3, toggleView = 'List');
    		onToggle(e.target.value);
    	};

    	const click_handler_1 = e => {
    		$$invalidate(3, toggleView = 'Grid');
    		onToggle(e.target.value);
    	};

    	$$self.$$set = $$props => {
    		if ('id' in $$props) $$invalidate(26, id = $$props.id);
    	};

    	$$self.$$.update = () => {
    		if ($$self.$$.dirty[0] & /*$page*/ 1) {
    			set_store_value(currentPage, $currentPage = $page, $currentPage);
    		}
    	};

    	return [
    		$page,
    		rowsCount,
    		loading,
    		toggleView,
    		$pageSize,
    		rows,
    		Drupal,
    		filterDefinitions,
    		sorts,
    		paginate,
    		pageSizes,
    		numberOfFilters,
    		numberOfSorts,
    		filters,
    		sort,
    		page,
    		pageSize,
    		focusedElement,
    		currentPage,
    		previousPage,
    		onPageChange,
    		onPageSizeChange,
    		onSort,
    		onFilterChange,
    		onToggle,
    		refreshLiveRegion,
    		id,
    		click_handler,
    		click_handler_1
    	];
    }

    class ProjectBrowser extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance$1, create_fragment$1, safe_not_equal, { id: 26 }, null, [-1, -1]);
    	}
    }

    /* src/App.svelte generated by Svelte v3.48.0 */

    function create_fragment(ctx) {
    	let projectbrowser;
    	let current;
    	projectbrowser = new ProjectBrowser({ props: { id: /*id*/ ctx[0] } });

    	return {
    		c() {
    			create_component(projectbrowser.$$.fragment);
    		},
    		m(target, anchor) {
    			mount_component(projectbrowser, target, anchor);
    			current = true;
    		},
    		p(ctx, [dirty]) {
    			const projectbrowser_changes = {};
    			if (dirty & /*id*/ 1) projectbrowser_changes.id = /*id*/ ctx[0];
    			projectbrowser.$set(projectbrowser_changes);
    		},
    		i(local) {
    			if (current) return;
    			transition_in(projectbrowser.$$.fragment, local);
    			current = true;
    		},
    		o(local) {
    			transition_out(projectbrowser.$$.fragment, local);
    			current = false;
    		},
    		d(detaching) {
    			destroy_component(projectbrowser, detaching);
    		}
    	};
    }

    function instance($$self, $$props, $$invalidate) {
    	let { id } = $$props;

    	// Removes initial loader if it exists.
    	const initialLoader = document.getElementById('initial-loader');

    	if (initialLoader) {
    		initialLoader.remove();
    	}

    	$$self.$$set = $$props => {
    		if ('id' in $$props) $$invalidate(0, id = $$props.id);
    	};

    	return [id];
    }

    class App extends SvelteComponent {
    	constructor(options) {
    		super();
    		init(this, options, instance, create_fragment, safe_not_equal, { id: 0 });
    	}
    }

    document.querySelectorAll('[data-project-browser-instance-id]').forEach((element) => {
      new App({
        // The #project-browser markup is returned by the project_browser.browse Drupal route.
        target: element,
        props: {
          id: element.getAttribute('data-project-browser-instance-id'),
        },
      });
    });

})();
//# sourceMappingURL=bundle.js.map
