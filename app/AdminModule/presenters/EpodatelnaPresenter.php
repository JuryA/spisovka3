<?php

class Admin_EpodatelnaPresenter extends BasePresenter
{

    private $info;

    public function renderDefault()
    {
        // Klientske nastaveni
        $ep_config = Config::fromFile(CLIENT_DIR .'/configs/epodatelna.ini');
        $ep = $ep_config->toArray();

        // ISDS
        $this->template->n_isds = $ep['isds'];

        // Email
        if ( count($ep['email'])>0 ) {
            $e_mail = array();

            $typ_serveru = array(
                            ''=>'',
                            '/pop3/novalidate-cert'=>'POP3',
                            '/pop3/ssl/novalidate-cert'=>'POP3-SSL',
                            '/imap/novalidate-cert'=>'IMAP',
                            '/imap/ssl/novalidate-cert'=>'IMAP+SSL',
                            '/nntp'=>'NNTP'
                );
            foreach ($ep['email'] as $ei => $email) {
                $email['protokol'] = $typ_serveru[ $email['typ'] ];
                $e_mail[$ei] = $email;
            }

            $this->template->n_email = $e_mail;
        } else {
            $this->template->n_email = null;
        }

        // Odeslani
        if ( count($ep['odeslani'])>0 ) {
            $e_odes = array();
            $typ_odes = array(
                          '0'=>'klasicky bez kvalifikovaného podpisu/značky',
                          '1'=>'s kvalifikovaným podpisem/značky'
                );
            foreach ($ep['odeslani'] as $eo => $odes) {

                $odes['zpusob_odeslani'] = $typ_odes[ $odes['typ_odeslani'] ];
                $e_odes[$eo] = $odes;
            }

            $this->template->n_odeslani = $e_odes;
        } else {
            $this->template->n_odeslani = null;
        }

        // CA
        $esign = new esignature();
        $esign->setCACert(LIBS_DIR .'/email/ca_certifikaty');
        //$this->template->n_ca = $esign->getCA();
        $this->template->n_ca = $esign->getCASimple();

    }

    public function renderDetail()
    {

        // Klientske nastaveni
        $ep_config = Config::fromFile(CLIENT_DIR .'/configs/epodatelna.ini');
        $ep = $ep_config->toArray();

        $id_alter = null;
        $do = $this->getParam('do');
        if ( $do ) {
            $id_index = $this->getHttpRequest()->getPost('index');
            $id_typ = $this->getHttpRequest()->getPost('ep_typ','i');
            $id_alter = $id_typ . $id_index;
        }
        
        
        $id = $this->getParam('id',$id_alter);
        $typ = substr($id,0,1);
        $index = substr($id,1);

        switch ($typ) {
            case 'i': 
                $crt = $ep['isds'][$index]['certifikat'];
                if ( file_exists($crt) ) {
                    $ep['isds'][$index]['certifikat_stav'] = 1;
                } else {
                    $ep['isds'][$index]['certifikat_stav'] = 0;
                }
                

                $this->info = @$ep['isds'][$index];
                break;
            case 'e': 
                $typ_serveru = array(
                                ''=>'',
                                '/pop3/novalidate-cert'=>'POP3',
                                '/pop3/ssl/novalidate-cert'=>'POP3-SSL',
                                '/imap/novalidate-cert'=>'IMAP',
                                '/imap/ssl/novalidate-cert'=>'IMAP+SSL',
                                '/nntp'=>'NNTP'
                    );
                @$ep['email'][$index]['protokol'] = $typ_serveru[ @$ep['email'][$index]['typ'] ];

                $this->info = @$ep['email'][$index];
                break;
            case 'o': 

                $typ_odeslani = array(
                        '0'=>'klasicky bez kvalifikovaného podpisu/značky',
                        '1'=>'s kvalifikovaným podpisem/značkou'
                );
                $ep['odeslani'][$index]['typ'] = $typ_odeslani[ $ep['odeslani'][$index]['typ_odeslani'] ];

                if ( file_exists($ep['odeslani'][$index]['cert']) ) {

                    $esign = new esignature();

                    if ( file_exists($ep['odeslani'][$index]['cert_key']) ) {
                        $cert_status = $esign->setUserCert($ep['odeslani'][$index]['cert'], $ep['odeslani'][$index]['cert_key'], $ep['odeslani'][$index]['cert_pass']);
                    } else {
                        $cert_status = $esign->setUserCert($ep['odeslani'][$index]['cert'], null, $ep['odeslani'][$index]['cert_pass']);
                    }

                    if ( $cert_status ) {
                        $ep['odeslani'][$index]['certifikat']['stav'] = 2; // existuje a je to certifikat, ale neni overen

                        $cert_info = $esign->getInfo();
                        if ( is_array($cert_info) ) {

                            if ( ($cert_info['platnost_od'] <= time()) && ($cert_info['platnost_do'] >= time()) ) {
                                $ep['odeslani'][$index]['certifikat']['stav'] = 4; // overen
                            } else {
                                $ep['odeslani'][$index]['certifikat']['stav'] = 3; // vyprsela platnost
                            }
                            $ep['odeslani'][$index]['certifikat']['info'] = $cert_info;
                        }

                    } else {
                        $ep['odeslani'][$index]['certifikat']['stav'] = 1; // existuje, ale neni to certifikat
                    }
                } else {
                    $ep['odeslani'][$index]['certifikat']['stav'] = 0; // neexistuje
                }


                $this->info = @$ep['odeslani'][$index];
                break;
            default: $this->info = null; break;
        }

        $this->template->Info = $this->info;
        $this->template->Typ = $typ;
        $this->template->Index = $index;


        // Zmena udaju
        $this->template->FormUpravit = $this->getParam('upravit',null);
        $this->template->FormHesloISDS = $this->getParam('zmenit_heslo_isds',null);

        if ( $do ) {
            $this->template->Index = $this->getHttpRequest()->getPost('index');
            $this->template->Typ = $this->getHttpRequest()->getPost('ep_typ','i');
            
            if ( $do == 'zmenit_heslo_isds' ) {
                $this->template->FormHesloISDS = 1;
            }
        }
    }



/**
 *
 * Formular a zpracovani pro zmenu udaju org. jednotky
 *
 */

    protected function createComponentNastavitISDSForm()
    {

        $ep_config = Config::fromFile(CLIENT_DIR .'/configs/epodatelna.ini');
        $ep = $ep_config->toArray();

        $id = $this->getParam('id',null);
        $typ = substr($id,0,1);
        $index = substr($id,1);
        $isds = $ep['isds'][$index];

        $org_select = array('0'=>'Kterákoli podatelna');
        $OrgJednotky = new Orgjednotka();
        if ( $orgjednotky_data = $OrgJednotky->seznam() ) {
            
        }

        $connect_type = array(
            '0' => 'Základní (jménem a heslem)',
            '1' => 'Spisovka (certifikátem)',
            '2' => 'Hostovaná spisovka (certifikátem + jménem a heslem)'
        );


        $form1 = new AppForm();
        $form1->addHidden('index')
                ->setValue($index);
        $form1->addHidden('ep_typ')
                ->setValue('i');
        $form1->addText('ucet', 'Název účtu:', 50, 100)
                ->setValue($isds['ucet'])
                ->addRule(Form::FILLED, 'Název účtu musí být vyplněno.');
        $form1->addCheckbox('aktivni', ' aktivní účet?')
                ->setValue($isds['aktivni']);

        $form1->addSelect('typ_pripojeni', 'Typ přihlášení:', $connect_type)
                ->setValue($isds['typ_pripojeni']);


        $form1->addText('login', 'Přihlašovací jméno od ISDS:', 50, 100)
                ->setValue($isds['login'])
                ->addRule(Form::FILLED, 'Přihlašovací jméno musí být vyplněno.');
        $form1->addText('password', 'Přihlašovací heslo ISDS:', 50, 100)
                ->setValue($isds['password'])
                ->addRule(Form::FILLED, 'Přihlašovací heslo musí být vyplněno.');

        $form1->addFile('certifikat_file', 'Cesta k certifikátu (formát X.509):');
        $form1->addText('cert_pass', 'Heslo k klíči certifikátu:', 50, 100)
                ->setValue($isds['cert_pass']);

        $form1->addSelect('test', 'Režim:', array('0'=>'Realný provoz (mojedatovaschranka.cz)',
                                                  '1'=>'Testovací režim (czebox.cz)'
                                            )
                )->setValue($isds['test']);

        $form1->addSelect('podatelna', 'Podatelna pro příjem:', $org_select)
                ->setValue($isds['podatelna']);

        $form1->addSubmit('upravit', 'Uložit')
                 ->onClick[] = array($this, 'nastavitISDSClicked');
        $form1->addSubmit('storno', 'Zrušit')
                 ->setValidationScope(FALSE)
                 ->onClick[] = array($this, 'stornoClicked');

        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');

        $renderer = $form1->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'dl';
        $renderer->wrappers['label']['container'] = 'dt';
        $renderer->wrappers['control']['container'] = 'dd';

        return $form1;
    }

    public function nastavitISDSClicked(SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $chyba = 0;

        $index = $data['index'];

        $config = Config::fromFile(CLIENT_DIR .'/configs/epodatelna.ini');
        $config_data = $config->toArray();

        $data['certifikat'] = "";
        if ( $data['typ_pripojeni'] == 1 || $data['typ_pripojeni'] == 2 ) {
            //nahrani certifikatu
            $upload = $data['certifikat_file'];
            if ( !file_exists(CLIENT_DIR ."/configs/files") ) {
                mkdir(CLIENT_DIR ."/configs/files");
            }

            if ( is_writeable(CLIENT_DIR ."/configs/files") ) {
                $fileName = CLIENT_DIR ."/configs/files/certifikat_isds". $index .".crt";
                if (!$upload instanceof HttpUploadedFile) {
                    $this->flashMessage('Certifikát se nepodařilo nahrát.','warning');
                } else if ( $upload->isOk() ) {
                    if ( $upload->move($fileName) ) {
                        $data['certifikat'] = $fileName;
                    } else {
                        $this->flashMessage('Certifikát se nepodařilo přenést na cílové místo.','warning');
                    }

                } else {
                    switch ($upload->error) {
                        case UPLOAD_ERR_INI_SIZE:
                            $this->flashMessage('Překročena velikost pro nahrání certifikátu.','warning');
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            //$this->flashMessage('Nebyl vybrán žádný soubor.','warning');
                            break;
                        default:
                            $this->flashMessage('Certifikát se nepodařilo nahrát.','warning');
                        break;
                    }
                }
            } else {
                // nelze nahrat
                $this->flashMessage('Certifikát nelze nahrát na cílové místo.','warning');
            }

        }
        unset($data['certifikat_file']);

        if ( $chyba == 0 ) {

            $config_data['isds'][$index]['ucet'] = $data['ucet'];
            $config_data['isds'][$index]['aktivni'] = $data['aktivni'];
            $config_data['isds'][$index]['typ_pripojeni'] = $data['typ_pripojeni'];
            $config_data['isds'][$index]['login'] = $data['login'];
            $config_data['isds'][$index]['password'] = $data['password'];
            if ( !empty($data['certifikat']) ) {
                $config_data['isds'][$index]['certifikat'] = $data['certifikat'];
            }
            $config_data['isds'][$index]['cert_pass'] = $data['cert_pass'];
            $config_data['isds'][$index]['test'] = $data['test'];
            $config_data['isds'][$index]['podatelna'] = $data['podatelna'];

            $idbox = "";
            $vlastnik = "";
            $stav = "";
            $chyba = 0;
            try {
                $ISDS = new ISDS_Spisovka();
                if ( $ISDS->pripojit($config_data['isds'][$index]) ) {
                    $info = $ISDS->informaceDS();
                    if ( !empty($info) ) {

                        $idbox = $info->dbID;
                        if ( empty($info->firmName) ) {
                            // jmeno prijmeni
                            $vlastnik = $info->pnFirstName ." ". $info->pnLastName ." [".$info->dbType."]";
                        } else {
                            // firma urad
                            $vlastnik = $info->firmName ." [".$info->dbType."]";
                        }
                        $stav = ISDS_Spisovka::stavDS($info->dbState) ." (kontrolováno dne ". date("j.n.Y G:i") .")";
                        $stav_hesla = $ISDS->GetPasswordInfo();
                    }
                } else {
                    $this->flashMessage('Nelze se připojit k ISDS! Chyba: '. $ISDS->error(),"warning");
                    //$this->redirect('this',array('id'=>('i' . $data['index']),'upravit'=>1));
                    $chyba = 1;
                }
            } catch (Exception $e) {
                $this->flashMessage('Nelze se připojit k ISDS! '. $e->getMessage(),"warning");
                //$this->redirect('this',array('id'=>('i' . $data['index']),'upravit'=>1));
                //$chyba = 1;
            }

            $config_data['isds'][$index]['idbox'] = $idbox;
            $config_data['isds'][$index]['vlastnik'] = $vlastnik;
            $config_data['isds'][$index]['stav'] = $stav;
            $config_data['isds'][$index]['stav_hesla'] = (empty($stav_hesla))?"(bez omezení)":date("j.n.Y G:i",strtotime($stav_hesla));

            $config_modify = new Config();
            $config_modify->import($config_data);
            $config_modify->save(CLIENT_DIR .'/configs/epodatelna.ini');

            Environment::setVariable('epodatelna_config', $config_modify);

            //if ( $chyba == 0 ) {
                $this->flashMessage('Nastavení datové schránky bylo upraveno.');
                $this->redirect('this',array('id'=>('i' . $data['index']) ));
            //}
        }
    }

    function ruleContains($item, $args)
    {
        return (strpos($item->value,$args) !== false);
    }
    
    function ruleNoEqual($item, $args)
    {
        return (strpos($item->value,$args) !== false);
    }     
    
    protected function createComponentZmenitHesloISDSForm()
    {

        $ep_config = Config::fromFile(CLIENT_DIR .'/configs/epodatelna.ini');
        $ep = $ep_config->toArray();

        $id = $this->getParam('id',null);
        $index = substr($id,1);
        $isds = $ep['isds'][$index];
        
        
        $id = $this->getParam('id',null);
        $index = substr($id,1);

        $form = new AppForm();
        $form->addHidden('index')
                ->setValue($index);
        $form->addHidden('zmenit_heslo_isds')
                ->setValue(1);        
        $form->addHidden('ep_typ')
                ->setValue('i');

        $form->addPassword('password', 'Přihlašovací heslo ISDS:', 30, 30)
                ->addRule(Form::FILLED, 'Heslo musí být vyplněné. Pokud nechcete změnit heslo, klikněte na tlačítko zrušit.')
                ->addRule(Form::MIN_LENGTH,'Heslo do datové schránky musí být minimálně %d znaků dlouhé.', 8)
                ->addRule(Form::MAX_LENGTH,'Heslo do datové schránky musí být maximálně %d znaků dlouhé.', 32)
                /*->addRule(callback($this, 'ruleNoEqual'),'Heslo mesmí obsahovat id (login) uživatele, jemuž se heslo mění.',$isds['login'])
                ->addRule(callback($this, 'ruleNoEqual'),'Heslo se nesmí shodovat s původním heslem.',$isds['password'])
                ->addRule(callback($this, 'ruleContains'),'Heslo nesmí začínat na "qwert", "asdgf", "12345"!','qwert')
                ->addRule(callback($this, 'ruleContains'),'Heslo nesmí začínat na "qwert", "asdgf", "12345"!','asdgf')
                ->addRule(callback($this, 'ruleContains'),'Heslo nesmí začínat na "qwert", "asdgf", "12345"!','12345')
                */;
                
        $form->addPassword('password_confirm', 'Přihlašovací heslo ještě jednou:', 30, 30)
                ->addRule(Form::FILLED, 'Heslo musí být vyplněné. Pokud nechcete změnit heslo, klikněte na tlačítko zrušit.')
                ->addConditionOn($form["password"], Form::FILLED)
                    ->addRule(Form::EQUAL, "Hesla se musí shodovat !", $form["password"]);        

        $form->addSubmit('zmenit', 'Změnit heslo')
                 ->onClick[] = array($this, 'zmenitHesloISDSClicked');
        $form->addSubmit('storno', 'Zrušit')
                 ->setValidationScope(FALSE)
                 ->onClick[] = array($this, 'stornoClicked');

        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'dl';
        $renderer->wrappers['label']['container'] = 'dt';
        $renderer->wrappers['control']['container'] = 'dd';

        return $form;
    }    
    
    public function zmenitHesloISDSClicked(SubmitButton $button)
    {
        $data = $button->getForm()->getValues();
        //echo "<pre>Data: "; print_r($data); echo "</pre>"; exit;

        $chyba = 0;

        $index = $data['index'];

        $config = Config::fromFile(CLIENT_DIR .'/configs/epodatelna.ini');
        $config_data = $config->toArray();
        $old_pass = $config_data['isds'][$index]['password'];

        if ( $chyba == 0 ) {

            $idbox = "";
            $vlastnik = "";
            $stav = "";
            try {
                $ISDS = new ISDS_Spisovka();
                if ( $ISDS->pripojit($config_data['isds'][$index]) ) {
                    
                    // zmena hesla
                    if ( $ISDS->ChangeISDSPassword($old_pass, $data['password']) ) {
                    //if ( false ) {
                        
                        $info = $ISDS->informaceDS();
                        if ( !empty($info) ) {

                            $idbox = $info->dbID;
                            if ( empty($info->firmName) ) {
                                // jmeno prijmeni
                                $vlastnik = $info->pnFirstName ." ". $info->pnLastName ." [".$info->dbType."]";
                            } else {
                                // firma urad
                                $vlastnik = $info->firmName ." [".$info->dbType."]";
                            }
                            $stav = ISDS_Spisovka::stavDS($info->dbState) ." (kontrolováno dne ". date("j.n.Y G:i") .")";
                            $stav_hesla = $ISDS->GetPasswordInfo();
                        }  
                        
                        $config_data['isds'][$index]['password'] = $data['password'];
                        $config_data['isds'][$index]['idbox'] = $idbox;
                        $config_data['isds'][$index]['vlastnik'] = $vlastnik;
                        $config_data['isds'][$index]['stav'] = $stav;
                        $config_data['isds'][$index]['stav_hesla'] = (empty($stav_hesla))?"(bez omezení)":date("j.n.Y G:i",strtotime($stav_hesla));

                        $config_modify = new Config();
                        $config_modify->import($config_data);
                        $config_modify->save(CLIENT_DIR .'/configs/epodatelna.ini');
                        
                        Environment::setVariable('epodatelna_config', $config_modify);                        
                        
                        $this->flashMessage('Heslo k datové schránky bylo úspěšně změněno.');
                        $this->redirect(':Admin:Epodatelna:detail',array('id'=>('i' . $data['index']) ));
                        
                    } else {
                        $this->flashMessage('Heslo k datové schránky se nepodařilo změnit.','warning');
                        $this->flashMessage('Chyba ISDS: '. $ISDS->error(),'warning');
                        //$this->redirect(':Admin:Epodatelna:detail',array('id'=>('i' . $data['index']),'zmenit_heslo_isds'=>'1' ));
                    }

                } else {
                    $this->flashMessage('Nelze se připojit k ISDS! Chyba: '. $ISDS->error(),"warning");
                    $this->redirect(':Admin:Epodatelna:detail',array('id'=>('i' . $data['index']) ));
                }
            } catch (Exception $e) {
                //$this->flashMessage('Nelze se připojit k ISDS! '. $e->getMessage(),"warning");
            }
        }
    }
    
    protected function createComponentNastavitEmailForm()
    {

        $ep_config = Config::fromFile(CLIENT_DIR .'/configs/epodatelna.ini');
        $ep = $ep_config->toArray();

        $id = $this->getParam('id',null);
        $typ = substr($id,0,1);
        $index = substr($id,1);
        $email = $ep['email'][$index];

        $org_select = array('0'=>'Kterákoli podatelna');
        $OrgJednotky = new Orgjednotka();
        if ( $orgjednotky_data = $OrgJednotky->seznam() ) {

        }

        $typ_serveru = array(
            ''=>'',
            '/pop3/novalidate-cert'=>'POP3 (standardní port 110)',
            '/pop3/ssl/novalidate-cert'=>'POP3-SSL (standardní port 995)',
            '/imap/novalidate-cert'=>'IMAP (standardní port 143)',
            '/imap/ssl/novalidate-cert'=>'IMAP+SSL (standardní port 993)',
            '/nntp'=>'NNTP (standardní port 119)'
        );

        $form1 = new AppForm();
        $form1->addHidden('index')
                ->setValue($index);
        $form1->addHidden('ep_typ')
                ->setValue('e');

        $form1->addText('ucet', 'Název účtu:', 50, 100)
                ->setValue($email['ucet'])
                ->addRule(Form::FILLED, 'Název účtu musí být vyplněno.');
        $form1->addCheckbox('aktivni', ' aktivní účet?')
                ->setValue($email['aktivni']);
        $form1->addSelect('typ', 'Protokol:', $typ_serveru)
                ->setValue($email['typ'])
                ->addRule(Form::FILLED, 'Vyberte protokol pro připojení k emailové schránce.');
        $form1->addText('server', 'Adresa serveru:', 50, 100)
                ->setValue($email['server'])
                ->addRule(Form::FILLED, 'Adresa poštovního serveru musí být vyplněna.');
        $form1->addText('port', 'Port:', 5, 50)
                ->setValue($email['port'])
                ->addRule(Form::FILLED, 'Port serveru musí být vyplněno.');
        $form1->addText('inbox', 'Složka:', 50, 100)
                ->setValue($email['inbox']);

        $form1->addText('login', 'Přihlašovací jméno:', 50, 100)
                ->setValue($email['login'])
                ->addRule(Form::FILLED, 'Přihlašovací jméno musí být vyplněno.');
        $form1->addText('password', 'Přihlašovací heslo:', 50, 100)
                ->setValue($email['password'])
                ->addRule(Form::FILLED, 'Přihlašovací heslo musí být vyplněno.');


        $form1->addSelect('podatelna', 'Podatelna pro příjem:', $org_select)
                ->setValue($email['podatelna']);

        $form1->addCheckbox('only_signature', 'přijímat pouze emaily s připojeným e-podpisem')
                ->setValue($email['only_signature']);
        $form1->addCheckbox('qual_signature', 'přijímat pouze emaily s ověřeným kvalifikovaným podpisem/značkou')
                ->setValue($email['qual_signature']);


       $form1->addSubmit('upravit', 'Uložit')
                 ->onClick[] = array($this, 'nastavitEmailClicked');
        $form1->addSubmit('storno', 'Zrušit')
                 ->setValidationScope(FALSE)
                 ->onClick[] = array($this, 'stornoClicked');

        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');

        $renderer = $form1->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'dl';
        $renderer->wrappers['label']['container'] = 'dt';
        $renderer->wrappers['control']['container'] = 'dd';

        return $form1;
    }

    public function nastavitEmailClicked(SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $index = $data['index'];

        $config = Config::fromFile(CLIENT_DIR .'/configs/epodatelna.ini');
        $config_data = $config->toArray();

        $config_data['email'][$index]['ucet'] = $data['ucet'];
        $config_data['email'][$index]['aktivni'] = $data['aktivni'];
        $config_data['email'][$index]['typ'] = $data['typ'];
        $config_data['email'][$index]['server'] = $data['server'];
        $config_data['email'][$index]['port'] = $data['port'];
        $config_data['email'][$index]['inbox'] = $data['inbox'];
        $config_data['email'][$index]['login'] = $data['login'];
        $config_data['email'][$index]['password'] = $data['password'];
        $config_data['email'][$index]['podatelna'] = $data['podatelna'];
        $config_data['email'][$index]['only_signature'] = $data['only_signature'];
        $config_data['email'][$index]['qual_signature'] = $data['qual_signature'];

        //Debug::dump($config_data); exit;
        $config_modify = new Config();
        $config_modify->import($config_data);
        $config_modify->save(CLIENT_DIR .'/configs/epodatelna.ini');

        Environment::setVariable('epodatelna_config', $config_modify);

        $this->flashMessage('Nastavení emailové schránky bylo upraveno.');
        $this->redirect('this',array('id'=>('e' . $data['index']) ));
    }

    protected function createComponentNastavitOdesForm()
    {

        $ep_config = Config::fromFile(CLIENT_DIR .'/configs/epodatelna.ini');
        $ep = $ep_config->toArray();

        $id = $this->getParam('id',null);
        $typ = substr($id,0,1);
        $index = substr($id,1);
        $odes = $ep['odeslani'][$index];

        $form1 = new AppForm();
        $form1->addHidden('index')
                ->setValue($index);
        $form1->addHidden('ep_typ')
                ->setValue($typ);
        $form1->addText('ucet', 'Název účtu:', 50, 100)
                ->setValue($odes['ucet'])
                ->addRule(Form::FILLED, 'Název účtu musí být vyplněno.');
        $form1->addCheckbox('aktivni', ' aktivní účet?')
                ->setValue($odes['aktivni']);
        $form1->addSelect('typ_odeslani', 'Jak odesílat:', array('0'=>'klasicky bez kvalifikovaného podpisu/značky',
                                                  '1'=>'s kvalifikovaným podpisem/značkou'
                                            )
                )->setValue($odes['typ_odeslani']);

        $form1->addText('email', 'Emailová adresa odesilatele:', 50, 100)
                ->setValue($odes['email']);

        $form1->addFile('cert_file', 'Cesta k certifikátu:');
        $form1->addFile('cert_key_file', 'Cesta k privátnímu klíči:');
        $form1->addText('cert_pass', 'Heslo k klíči certifikátu:', 50, 100)
                ->setValue($odes['cert_pass']);


        $form1->addSubmit('upravit', 'Uložit')
                 ->onClick[] = array($this, 'nastavitOdesClicked');
        $form1->addSubmit('storno', 'Zrušit')
                 ->setValidationScope(FALSE)
                 ->onClick[] = array($this, 'stornoClicked');

        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');

        $renderer = $form1->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'dl';
        $renderer->wrappers['label']['container'] = 'dt';
        $renderer->wrappers['control']['container'] = 'dd';

        return $form1;
    }

    public function nastavitOdesClicked(SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $index = $data['index'];

        $config = Config::fromFile(CLIENT_DIR .'/configs/epodatelna.ini');
        $config_data = $config->toArray();

        $data['cert'] = "";
        //nahrani certifikatu
        $upload = $data['cert_file'];
        if ( !file_exists(CLIENT_DIR .'/configs/files') ) {
            mkdir(CLIENT_DIR .'/configs/files');
        }

        $chyba_pri_uploadu = 0;
        {
            $fileName = CLIENT_DIR ."/configs/files/certifikat_email_". $index .".crt";
            if (!$upload instanceof HttpUploadedFile) {
                $this->flashMessage('Certifikát se nepodařilo nahrát.','warning');
            } else if ( $upload->isOk() ) {
                try {
                    $upload->move($fileName);
                    $data['cert'] = $fileName;
                } catch (Exception $e) {
                    $chyba_pri_uploadu = 1;
                    $this->flashMessage('Certifikát se nepodařilo přenést na cílové místo.','warning');
                }
            } else {
                switch ($upload->error) {
                    case UPLOAD_ERR_INI_SIZE:
                        $this->flashMessage('Překročena velikost pro nahrání certifikátu.','warning');
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        //$this->flashMessage('Nebyl vybrán žádný soubor.','warning');
                        break;
                    default:
                        $this->flashMessage('Certifikát se nepodařilo nahrát.','warning');
                    break;
                }
            }
        }
        unset($data['cert_file']);

        $data['cert_key'] = "";
        //nahrani privatniho klice
        $upload = $data['cert_key_file'];

        {
            $fileName = CLIENT_DIR ."/configs/files/certifikat_email_". $index .".key";
            if (!$upload instanceof HttpUploadedFile) {
                $this->flashMessage('Soubor privátního klíče se nepodařilo nahrát.','warning');
            } else if ( $upload->isOk() ) {
                try {
                    $upload->move($fileName);
                    $data['cert_key'] = $fileName;
                } catch (Exception $e) {
                    $chyba_pri_uploadu = 1;
                    $this->flashMessage('Soubor privátního klíče se nepodařilo přenést na cílové místo.','warning');
                }
            } else {
                switch ($upload->error) {
                    case UPLOAD_ERR_INI_SIZE:
                        $this->flashMessage('Překročena velikost pro nahrání souboru privátního klíče.','warning');
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        //$this->flashMessage('Nebyl vybrán žádný soubor.','warning');
                        break;
                    default:
                        $this->flashMessage('Soubor privátního klíče se nepodařilo nahrát.','warning');
                    break;
                }
            }
        }
        unset($data['cert_key_file']);

        if ($chyba_pri_uploadu && !is_writeable(CLIENT_DIR .'/configs/files'))
            $this->flashMessage('Nemohu zapisovat do adresáře client/configs/files/.','warning');
            
        $config_data['odeslani'][$index]['ucet'] = $data['ucet'];
        $config_data['odeslani'][$index]['aktivni'] = $data['aktivni'];
        $config_data['odeslani'][$index]['typ_odeslani'] = $data['typ_odeslani'];
        $config_data['odeslani'][$index]['email'] = $data['email'];

        if ( !empty($data['cert']) ) {
            $config_data['odeslani'][$index]['cert'] = $data['cert'];
            $config_data['odeslani'][$index]['cert_key'] = $data['cert_key'];
        }
        $config_data['odeslani'][$index]['cert_pass'] = $data['cert_pass'];

        //Debug::dump($config_data); exit;
        $config_modify = new Config();
        $config_modify->import($config_data);
        $config_modify->save(CLIENT_DIR .'/configs/epodatelna.ini');

        Environment::setVariable('epodatelna_config', $config_modify);

        $this->flashMessage('Nastavení odeslání emailu bylo upraveno.');
        $this->redirect('this',array('id'=>('o' . $data['index']) ));
    }

    public function stornoClicked(SubmitButton $button)
    {
        $data = $button->getForm()->getValues();
        $this->redirect('this',array('id'=>($data['ep_typ'] . $data['index']) ));
    }


}
