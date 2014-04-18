<?php

/**
 * Select2Component - formularovy prvek pro Select2 widget pro opakovane pouziti
 *
 * @author Petr Slavicek
 */
class Select2Component extends SelectBox implements IComponent
{
    public function __construct($label = NULL, array $items = NULL, $size = NULL)
    {
        parent::__construct($label, $items, $size);
        $this->controlPrototype->attrs['data-widget-select2'] = 1; //pouzit widget Select2
        //Select2 options resolve nefunguje u Ajax formularu
        $this->controlPrototype->attrs['data-widget-select2-options'] = json_encode(array('width' => 'resolve'));        
    }
    
    /**
     * nastavi option pro Select2 widget
     * @param string $name
     * @param string $value
     * @return \Select2Component
     */
    public function setSelect2Option($name, $value)
    {
        $options = isset($this->controlPrototype->attrs['data-widget-select2-options'])
            ? json_decode($this->controlPrototype->attrs['data-widget-select2-options']) : array();
        $options->$name = $value;
        $this->controlPrototype->attrs['data-widget-select2-options'] = json_encode($options);
        return $this;
    }
    
   
    
}
