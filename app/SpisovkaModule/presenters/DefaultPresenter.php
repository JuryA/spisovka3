<?php

class Spisovka_DefaultPresenter extends BasePresenter
{


    public function renderDefault()
    {

        $Acl = Acl::getInstance();
        if ( Environment::getUser()->isAllowed('Spisovka_DokumentyPresenter') ) {
            $this->redirect(':Spisovka:Dokumenty:default');
        }
        


    }


}