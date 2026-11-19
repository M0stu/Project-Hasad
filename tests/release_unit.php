<?php
declare(strict_types=1);
$GLOBALS['config']=[];$GLOBALS['lang']='en';require dirname(__DIR__).'/htdocs/app/helpers.php';
function check(bool $ok,string $label):void{if(!$ok){throw new RuntimeException('FAIL '.$label);}echo "PASS: $label\n";}
check(count(material_subcategories())===4,'four preparation subcategories');
$xml=spreadsheetml_document([['code'=>'=HYPERLINK("x")','name'=>'Rice straw']],'Stock');
check(str_contains($xml,'Workbook')&&str_contains($xml,'&#039;=HYPERLINK'),'SpreadsheetML workbook and formula neutralization');
check(subcategory_label('baled')==='Baled','localized subcategory');
echo "ALL RELEASE UNIT CHECKS PASSED.\n";
