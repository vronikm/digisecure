(function () {
    'use strict';

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }

        callback();
    }

    function normalizeSearch(value) {
        var raw = String(value || '').trim().toLowerCase();

        if (typeof raw.normalize === 'function') {
            try {
                return raw.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            } catch (error) {
                return raw;
            }
        }

        return raw
            .replace(/[áàäâã]/g, 'a')
            .replace(/[éèëê]/g, 'e')
            .replace(/[íìïî]/g, 'i')
            .replace(/[óòöôõ]/g, 'o')
            .replace(/[úùüû]/g, 'u')
            .replace(/ñ/g, 'n');
    }

    function setHidden(element, hidden) {
        if (!element) {
            return;
        }

        if (element.classList) {
            if (hidden) {
                element.classList.add('is-menu-hidden');
            } else {
                element.classList.remove('is-menu-hidden');
            }
        }
    }

    function setSubmenuOpen(node, open) {
        var submenu = node.querySelector('.ds-submenu');
        var trigger = node.querySelector('.ds-menu-item[data-bs-toggle="collapse"]');

        if (!submenu) {
            return;
        }

        submenu.classList.toggle('show', open);
        if (trigger) {
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function childItems(node) {
        return Array.prototype.slice.call(node.querySelectorAll('[data-menu-child]'));
    }

    function resetNode(node) {
        var children = childItems(node);

        setHidden(node, false);
        children.forEach(function (child) {
            setHidden(child, false);
        });

        setSubmenuOpen(node, Boolean(node.querySelector('.ds-menu-item.active, .ds-submenu-item.active')));
    }

    function initMenuSearch() {
        var searchInput = document.getElementById('ds-menu-search');
        var emptyState = document.getElementById('ds-menu-empty');
        var sections = Array.prototype.slice.call(document.querySelectorAll('[data-menu-section]'));

        if (!searchInput || sections.length === 0) {
            return;
        }

        function runSearch() {
            var query = normalizeSearch(searchInput.value);
            var visibleNodes = 0;

            sections.forEach(function (section) {
                var sectionVisible = 0;
                var nodes = Array.prototype.slice.call(section.querySelectorAll('[data-menu-node]'));

                nodes.forEach(function (node) {
                    if (query === '') {
                        resetNode(node);
                        sectionVisible += 1;
                        visibleNodes += 1;
                        return;
                    }

                    var ownText = normalizeSearch(node.getAttribute('data-menu-search-own'));
                    var fullText = normalizeSearch(node.getAttribute('data-menu-search'));
                    var children = childItems(node);
                    var ownMatches = ownText.indexOf(query) !== -1;
                    var visibleChildren = 0;

                    children.forEach(function (child) {
                        var childText = normalizeSearch(child.getAttribute('data-menu-search'));
                        var childMatches = ownMatches || childText.indexOf(query) !== -1;

                        setHidden(child, !childMatches);
                        if (childMatches) {
                            visibleChildren += 1;
                        }
                    });

                    var matches = children.length > 0
                        ? ownMatches || visibleChildren > 0
                        : fullText.indexOf(query) !== -1;

                    setHidden(node, !matches);
                    if (matches) {
                        sectionVisible += 1;
                        visibleNodes += 1;
                        setSubmenuOpen(node, children.length > 0);
                    } else {
                        setSubmenuOpen(node, false);
                    }
                });

                setHidden(section, sectionVisible === 0);
            });

            setHidden(emptyState, visibleNodes !== 0 || query === '');
        }

        ['input', 'keyup', 'change', 'search'].forEach(function (eventName) {
            searchInput.addEventListener(eventName, runSearch);
        });

        searchInput.setAttribute('data-menu-search-ready', '1');
    }

    onReady(initMenuSearch);
}());
