<?php
// index.php -- HotCRP home page
// HotCRP is Copyright (c) 2006-2007 Eddie Kohler and Regents of the UC
// Distributed under an MIT-like license; see LICENSE

require_once('Code/header.inc');
require_once('Code/paperlist.inc');
require_once('Code/search.inc');

if (!isset($_SESSION["Me"]) || !$_SESSION["Me"]->valid())
    go("login.php");
$Me = $_SESSION["Me"];

if (($_SESSION["AskedYouToUpdateContactInfo"] < 2
     && !($Me->lastName && $Me->affiliation))
    || ($_SESSION["AskedYouToUpdateContactInfo"] < 3 && $Me->isPC
	&& !($Me->collaborators || $Me->anyTopicInterest))) {
    $_SESSION["AskedYouToUpdateContactInfo"] = 1;
    $Me->go("account.php");
}

if ($Me->privChair && $Opt["globalSessionLifetime"] < $Opt["sessionLifetime"])
    $Conf->warnMsg("The systemwide <code>session.gc_maxlifetime</code> setting, which is " . htmlspecialchars($Opt["globalSessionLifetime"]) . " seconds, is less than HotCRP's preferred session expiration time, which is " . $Opt["sessionLifetime"] . " seconds.  You should update <code>session.gc_maxlifetime</code> in the <code>php.ini</code> file or users will likely be booted off the system earlier than you expect.");


$Conf->header("Home", "", actionBar(null, false, ""));


// if chair, check PHP setup
if ($Me->privChair) {
    if (get_magic_quotes_gpc())
	$Conf->errorMsg("The PHP <code>magic_quotes_gpc</code> feature is on.  This is a bad idea; disable it in your <code>php.ini</code> configuration file.");
}


// Submissions
$papersub = $Conf->setting("papersub");
if ($Me->privChair || ($Me->isPC && $papersub) || ($Me->amReviewer() && $papersub)) {
    echo "<hr class='smgap' />\n";
    echo "<table id='mainsub' class='center'><tr><td id='mainlist'>";

    // Lists
    echo "<strong>List papers: &nbsp;</strong> ";
    $sep = "";
    if ($Me->isReviewer) {
	echo $sep, "<a href='search.php?q=&amp;t=r' class='nowrap'>Your review assignment</a>";
	$sep = " &nbsp;|&nbsp; ";
    }
    if ($Me->isPC && $Conf->timePCViewAllReviews()
	&& $Me->amDiscussionLead(0, $Conf)) {
	echo $sep, "<a href=\"search.php?q=lead:", urlencode($Me->email), "&amp;t=s\" class='nowrap'>Your discussion lead</a>";
	$sep = " &nbsp;|&nbsp; ";
    }
    if ($Me->isPC && $papersub) {
	echo $sep, "<a href='search.php?q=&amp;t=s' class='nowrap'>Submitted</a>";
	$sep = " &nbsp;|&nbsp; ";
    }
    if ($Me->canViewDecision(null, $Conf) && $papersub) {
	echo $sep, "<a href='search.php?q=&amp;t=acc' class='nowrap'>Accepted</a>";
	$sep = " &nbsp;|&nbsp; ";
    }
    if ($Me->privChair) {
	echo $sep, "<a href='search.php?q=&amp;t=all' class='nowrap'>All</a>";
	$sep = " &nbsp;|&nbsp; ";
    }

    echo "</td></tr><tr><td id='mainsearch'>";
    echo "<form method='get' action='search.php'><input class='textlite' type='text' size='32' name='q' value='' /> &nbsp;<input class='button_small' type='submit' value='Search' /></form>\n";
    echo "<span class='sep'></span><small><a href='search.php?opt=1'>Advanced search</a></small>";
    echo "</td></tr></table>\n";
    echo "<hr class='main' />\n";
}




echo "<table class='half'><tr><td class='l'>";


// General information
echo "<div class='bgrp'><div class='bgrp_head'>General</div><div class='bgrp_body'>
Welcome, ", contactHtml($Me, null, ""), ".  (If this isn't you, please <a href='${ConfSiteBase}logout.php'>sign out</a>.)  You will be automatically signed out if you are idle for more than ", round(ini_get("session.gc_maxlifetime")/3600), " hours.\n";

// Conference settings
if ($Me->privChair)
    echo "<table class='half'><tr><td class='l'><ul class='compact'>
<li><a href='settings.php'><b>Conference settings</b></a></li>
</ul></td></tr></table>
<div class='smgap'></div>\n";

echo "<table class='half'><tr><td class='l'><ul class='compact'>
<li><a href='account.php'>Your account settings</a></li>
<li><a href='mergeaccounts.php'>Merge accounts</a></li>
</ul></td><td class='r'><ul class='compact'>\n";

// Any deadlines set?
if ($Conf->setting('sub_reg') || $Conf->setting('sub_update') || $Conf->setting('sub_sub')
    || ($Me->isAuthor && $Conf->setting('resp_open') > 0 && $Conf->setting('resp_done'))
    || ($Me->isPC && $Conf->setting('rev_open') && $Conf->setting('pcrev_hard'))
    || ($Me->amReviewer() && $Conf->setting('rev_open') && $Conf->setting('extrev_hard')))
    echo "<li><a href='deadlines.php'>Deadlines</a></li>\n";

echo "<li><a href='contacts.php?t=pc'>List program committee</a></li>\n";

echo "</ul></td></tr></table>";

if ($Me->privChair)
    echo "\n<div class='smgap'></div>
<table class='half'><tr><td class='l'><ul class='compact'>
<li><a href='contacts.php'>List accounts</a></li>
<li><a href='account.php?new=1'>Create new account</a></li>
<li><a href='Chair/BecomeSomeoneElse.php'>Sign in as someone else</a></li>
</ul></td><td class='r'><ul class='compact'>
<li><a href='mail.php'>Send users mail</a></li>
<li><a href='Chair/ViewActionLog.php'>View action log</a></li>
</ul></td></tr></table>";

echo "</div></div>\n";


echo "</td><td class='r'>";


// Authored papers
if ($Me->isAuthor || $Conf->timeStartPaper() > 0 || $Me->privChair) {
    echo "<div class='bgrp'><div class='bgrp_head'>Authored papers</div><div class='bgrp_body'>\n";
    $sep = "";

    $startable = $Conf->timeStartPaper();
    if ($startable || $Me->privChair) {
	echo $sep, "<div><strong><a href='paper.php?paperId=new'>Start new paper</a></strong> <span class='deadline'>(" . $Conf->printableDeadlineSetting('sub_reg') . ")</span>";
	if ($Me->privChair)
	    echo "<br/>\n<small>As an administrator, you can start papers regardless of deadlines and on other people's behalf.</small>";
	echo "</div>\n";
	$sep = "<div class='smgap'></div>";
    }

    if ($Me->isAuthor) {
	$plist = new PaperList(false, "listau", new PaperSearch($Me, array("t" => "a")));
	$plist->showHeader = 0;
	$ptext = $plist->text("authorHome", $Me);
	$deadlines = array();
	if ($plist->count > 0) {
	    echo $sep, $ptext;
	    $sep = "<div class='smgap'></div>";
	}
	if ($plist->needFinalize > 0) {
	    if (!$Conf->timeFinalizePaper())
		$deadlines[] = "The <a href='deadlines.php'>deadline</a> for submitting papers in progress has passed.";
	    else if (!$Conf->timeUpdatePaper()) {
		$deadlines[] = "The <a href='deadlines.php'>deadline</a> for updating papers in progress has passed, but you can still submit.";
		$time = $Conf->printableTimeSetting('sub_sub');
		if ($time != 'N/A')
		    $deadlines[] = "You have until $time to submit any papers in progress.";
	    } else if (($time = $Conf->printableTimeSetting('sub_update')) != 'N/A')
		$deadlines[] = "You have until $time to submit any papers in progress.";
	}
	if (!$startable && !$Conf->timeAuthorViewReviews())
	    $deadlines[] = "The <a href='deadlines.php'>deadline</a> for starting new papers has passed.";
	if (count($deadlines) > 0)
	    echo $sep, join("<br/>", $deadlines);
    }

    echo "</div></div>\n";
}


// Review assignment
if ($Me->amReviewer() && ($Me->privChair || $papersub)) {
    echo "<div class='bgrp foldc' id='foldre'><div class='bgrp_head'>";
    if ($Me->isReviewer)
	echo "<a href=\"javascript:fold('re', 0)\" class='foldbutton unfolder'>+</a><a href=\"javascript:fold('re', 1)\" class='foldbutton folder'>&minus;</a>&nbsp;";
    echo "Review assignments</div><div class='bgrp_body'>\n";
    $sep = "";

    echo "<table class='half'><tr><td class='l'><ul class='compact'>\n";
    if ($Me->isPC && $Conf->timePCReviewPreferences())
	echo "<li><a href='PC/reviewprefs.php'>Mark review preferences</a></li>\n";
    if ($Me->amReviewer())
	echo "<li><a href='offline.php'>Offline reviewing</a></li>\n";
    echo "</ul></td><td class='r'><ul class='compact'>\n";
    if ($Me->privChair)
	echo "<li><a href='Chair/AssignPapers.php'>PC review assignments and conflicts</a></li>\n";
    if ($Me->privChair || ($Me->isPC && $Conf->timePCViewAllReviews()))
	echo "<li><a href='contacts.php?t=pc'>Check on PC progress</a></li>\n";
    echo "</ul></td></tr></table>\n<div class='smgap'></div>\n";
    
    unset($plist);
    if ($Me->isReviewer) {
	$plist = new PaperList(false, "listre", new PaperSearch($Me, array("t" => "r")));
	$ptext = $plist->text("reviewerHome", $Me);
    }
    
    $deadlines = array();
    $rtyp = ($Me->isPC ? "pcrev_" : "extrev_");
    unset($d);
    if ($Me->isPC && $Conf->timeReviewPaper(true, false, true))
	$deadlines[] = "PC members may review <a href='search.php?q=&amp;t=s'>any submitted paper</a>, whether or not a review has been assigned.";
    if ((isset($plist) && $plist->needSubmitReview == 0) || !$Me->isReviewer)
	/* do nothing */;
    else if (!$Conf->timeReviewPaper($Me->isPC, true, true))
	$deadlines[] = "The <a href='deadlines.php'>deadline</a> for submitting " . ($Me->isPC ? "PC" : "external") . " reviews has passed.";
    else if (!$Conf->timeReviewPaper($Me->isPC, true, false))
	$deadlines[] = "Reviews were requested by " . $Conf->printableTimeSetting("${rtyp}soft") . ".";
    else {
	$d = $Conf->printableTimeSetting("${rtyp}soft");
	if ($d != "N/A")
	    $deadlines[] = "Please submit your reviews by $d.";
    }
    if ($Me->isPC && $Conf->timeReviewPaper(true, false, true)) {
	$d = (isset($d) ? "N/A" : $Conf->printableTimeSetting("pcrev_soft"));
	if ($d != "N/A")
	    $deadlines[] = "Please submit your reviews by $d.";
    }
    if (count($deadlines) > 0) {
	echo $sep, join("<br />", $deadlines);
	$sep = "<div class='smgap'></div>";
    }

    if (isset($plist) && $plist->count > 0) {
	echo "<div class='smgap extension'>", $ptext, "</div>";
	$sep = "<div class='smgap'></div>";
    }

    echo "</div></div>\n";
}


// PC tasks (old CRP)
if ($Me->isPC) {
    echo "<div class='bgrp foldc' id='foldpc'><div class='bgrp_head'><a href=\"javascript:fold('pc', 0)\" class='foldbutton unfolder'>+</a><a href=\"javascript:fold('pc', 1)\" class='foldbutton folder'>&minus;</a>&nbsp;PC member tasks (old CRP)</div><div class='bgrp_body extension'>\n";

    echo "<ul>
<li>Reviewer assignments  (asking others to review papers)
  <ul>
  <li><a href='PC/CheckReviewStatus.php'>Check on reviewer progress</a> (and possibly nag reviewers)</li>
  </ul></li>

<li>Check on reviewer progress
  <ul>\n";
    if ($Conf->timePCViewAllReviews() || $Me->privChair) {
	echo "  <li><a href='Chair/AverageReviewerScore.php'>See average reviewer ratings</a> -- this compares the overall merit ratings of different reviewers</li>\n";
    }
    echo "</ul></li>\n";

    if ($Me->privChair && isset($Opt['dbDumpDir']))
	echo "<li><a href='Chair/DumpDatabase.php'>Make a backup of the database</a></li>\n";

    echo "</ul></div></div>\n";
}    


echo "</td></tr></table>\n";


unset($_SESSION["list"]);
$Conf->footer();
