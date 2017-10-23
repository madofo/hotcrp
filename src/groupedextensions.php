<?php
// src/groupedextensions.php -- HotCRP settings > decisions page
// HotCRP is Copyright (c) 2006-2017 Eddie Kohler and Regents of the UC
// Distributed under an MIT-like license; see LICENSE

class GroupedExtensions {
    private $_subgroups;

    function _add_json($fj) {
        if (isset($fj->name) && is_string($fj->name)) {
            if (!isset($fj->group))
                $fj->group = $fj->name;
            if (!isset($fj->synonym))
                $fj->synonym = [];
            else if (is_string($fj->synonym))
                $fj->synonym = [$fj->synonym];
            $this->_subgroups[] = $fj;
            return true;
        } else
            return false;
    }
    function __construct(Contact $user, $args /* ... */) {
        $this->_subgroups = [];
        foreach (func_get_args() as $i => $arg) {
            if ($i > 0 && $arg)
                expand_json_includes_callback($arg, [$this, "_add_json"]);
        }
        usort($this->_subgroups, "Conf::xt_priority_compare");
        $gs = $sgs = $known = [];
        foreach ($this->_subgroups as $gj) {
            if (isset($known[$gj->name]) || !$user->conf->xt_allowed($gj, $user))
                continue;
            $known[$gj->name] = true;
            foreach ($gj->synonym as $syn)
                $known[$syn] = true;
            if (Conf::xt_enabled($gj)) {
                if ($gj->group === $gj->name)
                    $gs[$gj->name] = $gj;
                $sgs[$gj->name] = $gj;
            }
        }
        $this->_subgroups = array_filter($sgs, function ($gj) use ($gs) {
            return isset($gs[$gj->group]);
        });
        uasort($this->_subgroups, function ($aj, $bj) use ($gs) {
            if ($aj->group !== $bj->group)
                return Conf::xt_position_compare($gs[$aj->group], $gs[$bj->group]);
            else
                return Conf::xt_position_compare($aj, $bj);
        });
    }
    function get($name) {
        if (isset($this->_subgroups[$name]))
            return $this->_subgroups[$name];
        foreach ($this->_subgroups as $gj) {
            if (in_array($name, $gj->synonym))
                return $gj;
        }
        return null;
    }
    function canonical_group($name) {
        $gj = $this->get($name);
        return $gj ? $gj->group : false;
    }
    function members($name) {
        $name = $this->canonical_group($name);
        return array_filter($this->_subgroups, function ($gj) use ($name) {
            return $gj->group === $name;
        });
    }
    function all() {
        return $this->_subgroups;
    }
    function groups() {
        return array_filter($this->_subgroups, function ($gj) {
            return $gj->name === $gj->group;
        });
    }
}
