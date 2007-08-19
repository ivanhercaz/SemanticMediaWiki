<?php
/**
 * @author Udi Oron אודי אורון
 */

global $smwgIP;
include_once($smwgIP . '/languages/SMW_Language.php');

class SMW_LanguageHe extends SMW_Language {

protected $smwContentMessages = array(
	'smw_edithelp' => 'עזרה בנושא עריכת יחסים ותכונות',
	'smw_helppage' => 'יחס',
	'smw_viewasrdf' => 'RDF feed',
	'smw_finallistconjunct' => ', וגם', //used in "A, B, and C"
	'smw_factbox_head' => 'עובדות על אודות $1 &mdash; לחץ <span class="smwsearchicon">+</span> בכדי למצוא דפים דומים.',
	'smw_spec_head' => 'מאפיינים מיוחדים',
	/*URIs that should not be used in objects in cases where users can provide URIs */
	'smw_uri_blacklist' => " http://www.w3.org/1999/02/22-rdf-syntax-ns#\n http://www.w3.org/2000/01/rdf-schema#\n http://www.w3.org/2002/07/owl#",
	'smw_baduri' => 'Sorry, URIs from the range "$1" are not available in this place.',
	/*Messages and strings for inline queries*/
	'smw_iq_disabled' => "<span class='smwwarning'>Sorry. Inline queries have been disabled for this wiki.</span>", // TODO: translate
	'smw_iq_moreresults' => '&hellip; תוצאות נוספות',
	'smw_iq_nojs' => 'Use a JavaScript-enabled browser to view this element, or directly <a href="$1">browse the result list</a>.', //TODO: translate
	/*Messages and strings for ontology resued (import) */
	'smw_unknown_importns' => '[Sorry, import functions are not avalable for namespace "$1".]', // TODO: translate
	'smw_nonright_importtype' => '[Sorry, $1 can only be used for pages with namespace "$2"]',
	'smw_wrong_importtype' => '[Sorry, $1 can not be used for pages in the namespace "$2"]',
	'smw_no_importelement' => '[Sorry, element "$1" not available for import.]',
	/*Messages and strings for basic datatype processing*/
	'smw_decseparator' => '.',
	'smw_kiloseparator' => ',',
	'smw_unknowntype' => '[אופס! טיפוס לא מוכר "$1" הוגדר עבור תכונה זו]',
	'smw_manytypes' => '[אופס! הוגדר יותר מטיפוס אחד לתכונה זו]',
	'smw_emptystring' => '[אופס! לא ניתן להשתמש כאן במחרוזות ריקות]',
	'smw_maxstring' => '[מצטערת, ייצוג המחרוזת כ-$1 ארוך מדי עבור אתר זה.]',
	'smw_nopossiblevalues' => '[אופס! הערכים האפשריים לתכונה זו לא הוגדרו]',
	'smw_notinenum' => '[אופס! "$1" לא נמצא בערכים האפשריים ($2) לתכונה זו]',
	'smw_noboolean' => '[אופס! "$1" אינה תכונה מטיפוס נכון-לאנכון]',
	'smw_true_words' => 't,yes,y,כן,נכון,אמת,חיובי,כ',	// comma-separated synonyms for boolean TRUE besides 'true' and '1'
	'smw_false_words' => 'f,no,n,לא,לא נכון,לא-נכון,שקר,שלישי,ל',	// comma-separated synonyms for boolean FALSE besides 'false' and '0'
	'smw_nointeger' => '[אופס! "$1" אינו מספר שלם]',
	'smw_nofloat' => '[אופס! "$1" אינו מספר מטיפוס נקודה צפה]',
	'smw_infinite' => '[מצטרת, $1 הוא מספר גדול מדי לאתר זה .]',
	'smw_infinite_unit' => '[מצטערת, תוצאת ההמרה ליחידה $1 היא מספר גדול מדי לאתר זה.]',
	'smw_unexpectedunit' => 'תכונה זו אינה תומכת בהמרה מטיפוס לטיפוס',
	'smw_unsupportedunit' => 'אין תמיכה להמרת יחידות לטיפוס "$1"',
	/*Messages for geo coordinates parsing*/
	'smw_err_latitude' => 'הערכים לקו-רוחב (צפון, דרום) ,צריכים להיות בין 0 ל-90. "$1" הינו ערך שאינו עומד בדרישה זו!',
	'smw_err_longitude' => 'הערכים לקו-אורך )מזרח, מערב)חייבים להיות בין 0 ל-180. "$1" הינו ערך שאינו עומד בדרישה זו!',
	'smw_err_noDirection' => '[אופס!  משהו אינו כשורה עם הערך שצוין "$1"]',
	'smw_err_parsingLatLong' => '[אופס!  משהו אינו כשורה עם הערך שצוין "$1". ציפיתי למשהו בסגנון "1°2′3.4′′ מערב"!]',
	'smw_err_wrongSyntax' => '[אופס!  משהו לא בסדר עם הערך שצוין "$1". ציפיתי למשהו בסגנון "1°2′3.4′′ W, 5°6′7.8′′ צפון" !]',
	'smw_err_sepSyntax' => 'הרך שהוזן "$1" נראה בסדר, אבל ערכים לקוי-רוחב וקוי גובה צריכים להיות מופרדים ב "," או ב ";".',
	'smw_err_notBothGiven' => 'עליך להזין ערך חוקי לקו האורך (מערב, מזרח) וגם לקו הגובה (צפון, דרום)! אחד מהערכים לפחות חסר!',
	/* additionals ... */
	'smw_label_latitude' => 'קו רוחב:',
	'smw_label_longitude' => 'קו אורך:',
	'smw_abb_north' => 'צפון',
	'smw_abb_east' => 'מזרח',
	'smw_abb_south' => 'דרום',
	'smw_abb_west' => 'מערב',
	/* some links for online maps; can be translated to different language versions of services, but need not*/
	'smw_service_online_maps' => " חפש&nbsp;מפות|http://tools.wikimedia.de/~magnus/geo/geohack.php?language=he&params=\$9_\$7_\$10_\$8\n Google&nbsp;maps|http://maps.google.com/maps?ll=\$11\$9,\$12\$10&spn=0.1,0.1&t=k\n Mapquest|http://www.mapquest.com/maps/map.adp?searchtype=address&formtype=latlong&latlongtype=degrees&latdeg=\$11\$1&latmin=\$3&latsec=\$5&longdeg=\$12\$2&longmin=\$4&longsec=\$6&zoom=6",
	/*Messages for datetime parsing */
	'smw_nodatetime' => '[אופס! התאריך "$1" אינו מובן. מצד שני התמיכה בתאריכים היא עדיין ניסיונית.]'
);


protected $smwUserMessages = array(
	'smw_devel_warning' => 'This feature is currently under development, and might not be fully functional. Backup your data before using it.',
	// Messages for article pages of types, relations, and attributes
	'smw_type_header' => 'Attributes of type “$1”', // TODO translate
	'smw_typearticlecount' => 'Showing $1 attributes using this type.', // TODO translate
	'smw_attribute_header' => 'Pages using the attribute “$1”', // TODO translate
	'smw_attributearticlecount' => '<p>Showing $1 pages using this attribute.</p>', // TODO translate
	/*Messages for Export RDF Special*/ // TODO: translate
	'exportrdf' => 'Export pages to RDF', //name of this special
	'smw_exportrdf_docu' => '<p>This page allows you to obtain data from a page in RDF format. To export pages, enter the titles in the text box below, one title per line.</p>',
	'smw_exportrdf_recursive' => 'Recursively export all related pages. Note that the result could be large!',
	'smw_exportrdf_backlinks' => 'Also export all pages that refer to the exported pages. Generates browsable RDF.',
	// Messages for Properties Special
	'properties' => 'Properties', //TODO: translate
	'smw_properties_docu' => 'The following properties are used in the wiki.', //TODO: translate
	'smw_property_template' => '$1 of type $2 ($3)', // <propname> of type <type> (<count>) //TODO: translate
	'smw_propertylackspage' => 'All properties should be described by a page!', //TODO: translate
	'smw_propertylackstype' => 'No type was specified for this property (assuming type $1 for now).', //TODO: translate
	'smw_propertyhardlyused' => 'This property is hardly used within the wiki!', //TODO: translate
	// Messages for Unused Properties Special
	'unusedproperties' => 'Unused Properties', //TODO: translate
	'smw_unusedproperties_docu' => 'The following properties exist although no other page makes use of them.', //TODO: translate
	'smw_unusedproperty_template' => '$1 of type $2', // <propname> of type <type> //TODO: translate
	// Messages for Wanted Properties Special
	'wantedproperties' => 'Wanted Properties', //TODO: translate
	'smw_wantedproperties_docu' => 'The following properties are used in the wiki but do not yet have a page for describing them.', //TODO: translate
	'smw_wantedproperty_template' => '$1 ($2 uses)', // <propname> (<count> uses) //TODO: translate
//// Note to translators:
//// The following messages in comments were kept for reference to facilitate the translation of the property messages above.
//// Delete them when no longer needed.
// 	/*Messages for Relations Special*/
// 	'relations' => 'יחסים',
// 	'smw_relations_docu' => 'היחסים הבאים מופיעים באתר.',
// 	// Messages for WantedRelations Special
// 	'wantedrelations' => 'Wanted relations', //TODO: translate
// 	'smw_wanted_relations' => 'The following relations do not have an explanatory page yet, though they are already used to describe other pages.', //TODO: translate
// 	/*Messages for Attributes Special*/
// 	'attributes' => 'תכונות',
// 	'smw_attributes_docu' => 'התכונות הבאות קיימים באתר.',
// 	'smw_attr_type_join' => ' עם $1',
// 	/*Messages for Unused Relations Special*/
// 	'unusedrelations' => 'יחסים שאינם בשימוש',
// 	'smw_unusedrelations_docu' => 'היחסים הבאים מוגדרים באתר אך לא נעשה בהם כל שימוש.',
// 	/*Messages for Unused Attributes Special*/
// 	'unusedattributes' => 'תכונות שאינן בשימוש',
// 	'smw_unusedattributes_docu' => 'התכונות הבאות מוגדרים במערכת אך לא נעשה בהם שימוש.',
	/* Messages for the refresh button */
	'tooltip-purge' => 'לחץ כאן הכדי לרענן את כל התבניות והשאילתות בדף זה',
	'purge' => 'רענן תבניות ושאילתות',
	/*Messages for Import Ontology Special*/
	'ontologyimport' => 'Import ontology',
	'smw_oi_docu' => 'This special page allows to import ontologies. The ontologies have to follow a certain format, specified at the <a href="http://wiki.ontoworld.org/index.php/Help:Ontology_import">ontology import help page</a>.',
	'smw_oi_action' => 'Import',
	'smw_oi_return' => 'Return to <a href="$1">Special:OntologyImport</a>.',
	'smw_oi_noontology' => 'No ontology supplied, or could not load ontology.',
	'smw_oi_select' => 'Please select the statements to import, and then click the import button.',
	'smw_oi_textforall' => 'Header text to add to all imports (may be empty):',
	'smw_oi_selectall' => 'Select or unselect all statements',
	'smw_oi_statementsabout' => 'Statements about',
	'smw_oi_mapto' => 'Map entity to',
	'smw_oi_comment' => 'Add the following text:',
	'smw_oi_thisissubcategoryof' => 'A subcategory of',
	'smw_oi_thishascategory' => 'Is part of',
	'smw_oi_importedfromontology' => 'Import from ontology',
	/*Messages for (data)Types Special*/
	'types' => 'טיפוסים',
	'smw_types_docu' => 'ברשימה זו מופיעים כל טיפוסי המידע שתכונות יכולות להשתמש בהם . לכל טיפוס מידע יש דף המסביר על אודותיו.',
	'smw_types_units' => 'יחיסה סטנדרטית: $1; תומכת ביחידות: $2',
	'smw_types_builtin' => 'טיפוסים מובנים',
	/*Messages for SemanticStatistics Special*/
	'semanticstatistics' => 'Semantic Statistics', // TODO: translate
	'smw_semstats_text' => 'This wiki contains <b>$1</b> property values for a total of <b>$2</b> different <a href="$3">properties</a>. <b>$4</b> properties have an own page, and the intended datatype is specified for <b>$5</b> of those. Some of the existing properties might by <a href="$6">unused properties</a>. Properties that still lack a page are found on the <a href="$7">list of wanted properties</a>.', // TODO: translate
	/*Messages for Flawed Attributes Special --disabled--*/
	'flawedattributes' => 'Flawed Attributes',
	'smw_fattributes' => 'The pages listed below have an incorrectly defined attribute. The number of incorrect attributes is given in the brackets.',
	// Name of the URI Resolver Special (no content)
	'uriresolver' => 'URI Resolver', //TODO: translate
	'smw_uri_doc' => '<p>The URI resolver implements the <a href="http://www.w3.org/2001/tag/issues.html#httpRange-14">W3C TAG finding on httpRange-14</a>. It takes care that humans don\'t turn into websites.</p>', //TODO: translate
	/*Messages for ask Special*/
	'ask' => 'חיפוש סמנטי',
	'smw_ask_docu' => '<p>Search pages by entering a query into the search field below. Further information is given on the <a href="$1">help page for semantic search</a>.</p>',
	'smw_ask_doculink' => 'חיפוש סמנטי',
	'smw_ask_sortby' => 'מיין לפי טור',
	'smw_ask_ascorder' => 'בסדר עולה',
	'smw_ask_descorder' => 'בסדר יורד',
	'smw_ask_submit' => 'חפש תוצאות',
	// Messages for the search by property special TODO: translate
	'searchbyproperty' => 'Search by property', //TODO: translate
	'smw_sbv_docu' => '<p>Search for all pages that have a given property and value.</p>', //TODO: translate
	'smw_sbv_noproperty' => '<p>Please enter a property.</p>', //TODO: translate
	'smw_sbv_novalue' => '<p>Please enter a valid value for the property, or view all property values for “$1.”</p>', //TODO: translate
	'smw_sbv_displayresult' => 'A list of all pages that have property “$1” with value “$2”', //TODO: translate
	'smw_sbv_property' => 'Property', //TODO: translate
	'smw_sbv_value' => 'Value', //TODO: translate
	'smw_sbv_submit' => 'Find results', //TODO: translate
	// Messages for the browsing system
	'browse' => 'Browse wiki', //TODO: translate
	'smw_browse_article' => 'Enter the name of the page to start browsing from.', //TODO: translate
	'smw_browse_go' => 'Go', //TODO: translate
	'smw_browse_more' => '&hellip;', //TODO: translate
	// Messages for the page property special
	'pageproperty' => 'Page property search', // TODO: translate
	'smw_pp_docu' => 'Search for all the fillers of a property on a given page. Please enter both a page and a property.', // TODO: translate
	'smw_pp_from' => 'From page', // TODO: translate
	'smw_pp_type' => 'Property', // TODO: translate
	'smw_pp_submit' => 'Find results', // TODO: translate
	// Generic messages for result navigation in all kinds of search pages
	'smw_result_prev' => 'הקודם',
	'smw_result_next' => 'הבא',
	'smw_result_results' => 'תוצאות',
	'smw_result_noresults' => 'מצטערת, אין תוצאות'
);

protected $smwDatatypeLabels = array(
	'smw_wikipage' => 'Page', // name of page datatype  //TODO translate
	'smw_string' => 'מחרוזת',  // name of the string type
	'smw_text' => 'Text',  // name of the text type (very long strings) //TODO: translate
	'smw_enum' => 'Enumeration',  // name of the enum type
	'smw_bool' => 'נכוןלאנכון',  // name of the boolean type
	'smw_int' => 'שלם',  // name of the int type
	'smw_float' => 'נקודהצפה',  // name of the floating point type
	'smw_length' => 'מרחק',  // name of the length type
	'smw_area' => 'שטח',  // name of the area type
	'smw_geolength' => 'מרחק גיאוגרפי',  // OBSOLETE name of the geolength type
	'smw_geoarea' => 'שטח גיאוגרפי',  // OBSOLETE name of the geoarea type
	'smw_geocoordinate' => 'קורדינטות גיאוגרפיות', // name of the geocoord type
	'smw_mass' => 'מסה',  // name of the mass type
	'smw_time' => 'זמן',  // name of the time (duration) type
	'smw_temperature' => 'טמפרטורה',  // name of the temperature type
	'smw_datetime' => 'תאריך',  // name of the datetime (calendar) type
	'smw_email' => 'דואל',  // name of the email (URI) type
	'smw_url' => 'URL',  // name of the URL type (string datatype property)
	'smw_uri' => 'מזהה יחודי',  // name of the URI type (object property)
	'smw_annouri' => 'Annotation URI'  // name of the annotation URI type (annotation property)
);

protected $smwSpecialProperties = array(
	//always start upper-case
	SMW_SP_HAS_TYPE  => 'מטיפוס',
	SMW_SP_HAS_URI   => 'מזהה יחודי תואם',
	SMW_SP_SUBPROPERTY_OF => 'Subproperty of', // TODO: translate
	SMW_SP_MAIN_DISPLAY_UNIT => 'יחידת הצגה ראשית',
	SMW_SP_DISPLAY_UNIT => 'יחידת הצגה',
	SMW_SP_IMPORTED_FROM => 'יובא מ',
	SMW_SP_CONVERSION_FACTOR => 'מתורגם ל',
	SMW_SP_CONVERSION_FACTOR_SI => 'Corresponds to SI', //TODO: translate
	SMW_SP_SERVICE_LINK => 'מספק שירות',
	SMW_SP_POSSIBLE_VALUE => 'ערכים אפשריים' //   TODO: check translation, should be singular value//
);


	/**
	 * Function that returns the namespace identifiers.
	 */
	public function getNamespaceArray() {
		return array(
			SMW_NS_RELATION       => 'יחס',
			SMW_NS_RELATION_TALK  => 'שיחת_יחס',
			SMW_NS_PROPERTY       => 'תכונה',
			SMW_NS_PROPERTY_TALK  => 'שיחת_תכונה',
			SMW_NS_TYPE           => 'טיפוס',
			SMW_NS_TYPE_TALK      => 'שיחת_טיפוס'
		);
	}
}
