<?php
 /**
  * Nette\Extras DateTimePicker with jQuery UI 1.7.2 example
  */

 require_once('Nette/loader.php');
 require_once('Extras/DateTimePicker.php');

 Debug::enable();

 // Budoucí metoda Form::addDateTimePicker()
 function Form_addDateTimePicker(Form $_this, $name, $label, $cols = NULL, $maxLength = NULL)
 {
   return $_this[$name] = new DateTimePicker($label, $cols, $maxLength);
 }

 Form::extensionMethod('Form::addDateTimePicker', 'Form_addDateTimePicker');  // v PHP 5.2
 //Form::extensionMethod('addDateTimePicker', 'Form_addDateTimePicker');  // v PHP 5.3

 $form = new Form();

 $form->addDateTimePicker('datum_cas', 'Kdy a v kolik to bude?', 16, 16)
      ->addRule(Form::FILLED, 'Zadejte prosím datum a čas.');

 $form->addSubmit('submit_datetime', 'Odeslat');

 if ($form->isSubmitted())
 {
   if ($form->isValid())
   {
     echo '<h2>Form was submitted and successfully validated</h2>';

     $values = $form->getValues();

     echo '<pre>';
     print_r($values);
     echo '</pre>';

     exit;
   }
 }
 //else
   //$form->setDefaults(array('datum_cas' => '2009-12-09 13:54'));
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="cs" lang="cs">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta http-equiv="Content-Language" content="cs" />
  <title>Nette\Extras DateTimePicker with jQuery UI 1.7.2 example</title>
  <link rel="stylesheet" href="http://jquery-ui.googlecode.com/svn/tags/1.7.2/themes/blitzer/ui.all.css" type="text/css" />
  <script type="text/javascript" src="http://code.jquery.com/jquery-latest.js"></script>
  <script type="text/javascript" src="http://jquery-ui.googlecode.com/svn/tags/1.7.2/ui/minified/ui.core.min.js"></script>
  <script type="text/javascript" src="http://jquery-ui.googlecode.com/svn/tags/1.7.2/ui/minified/ui.slider.min.js"></script>
  <script type="text/javascript" src="http://jquery-ui.googlecode.com/svn/tags/1.7.2/ui/minified/ui.datepicker.min.js"></script>
  <script type="text/javascript" src="http://jquery-ui.googlecode.com/svn/tags/1.7.2/ui/minified/i18n/ui.datepicker-cs.min.js"></script>
  <script type="text/javascript" src="timepicker-cs.js"></script>
  <script type="text/javascript">
  <!-- <![CDATA[
    $(document).ready(function()
    {
      $('input.datetimepicker').datepicker(
      {
        duration: '',
        changeMonth: true,
        changeYear: true,
        yearRange: '2007:2020',
        showTime: true,
        time24h: true,
        currentText: 'Dnes',
        closeText: 'OK'
      });
    });
  //]]> -->
  </script>
  <style type="text/css">
  <!--
    body
    {
      font-family: 'Tahoma', 'Verdana', 'Arial';
      background-color: #FFFFFF;
      color: #000000;
      font-size: 70%;
    }

    input.datetimepicker
    {
      font-family: 'Tahoma', 'Verdana', 'Arial';
      font-size: 100%;
      border: 1px solid #C0C0C0;
      padding: 1.5pt 6pt 1.5pt 1.5pt;
      background: transparent url('calendar.png') no-repeat right;
    }

    input.button
    {
      font-family: 'Verdana', 'Tahoma', 'Arial';
      font-size: 100%;
    }
  //-->
  </style>
</head>
<body>
  <h1>Nette\Extras DateTimePicker with jQuery UI 1.7.2 example</h1>
<?php
 echo $form;
?>
</body>
</html>