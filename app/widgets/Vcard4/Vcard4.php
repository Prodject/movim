<?php

use Movim\Widget\Base;

use Moxl\Xec\Action\Vcard4\Get;
use Moxl\Xec\Action\Vcard4\Set;
use Moxl\Xec\Action\Nickname\Set as Nickname;

use Respect\Validation\Validator;

class Vcard4 extends Base
{
    public function load()
    {
        $this->registerEvent('vcard4_get_handle', 'onMyVcard4');
        $this->registerEvent('vcard4_set_handle', 'onMyVcard4');
    }

    public function prepareForm($contact)
    {
        $vcardform = $this->tpl();

        $vcardform->assign('me',       $this->user);
        $vcardform->assign('contact',  $contact);
        $vcardform->assign('desc',     trim($contact->description));
        $vcardform->assign('countries',getCountries());

        $contact->isValidDate();

        $vcardform->assign(
            'submit',
            $this->call('ajaxVcardSubmit', "MovimUtils.formToJson('vcard4')")
            );

        return $vcardform->draw('_vcard4_form');
    }

    public function onMyVcard4($packet)
    {
        $html = $this->prepareForm($packet->content);

        Notification::append(null, $this->__('vcard.updated'));

        $this->rpc('MovimTpl.fill', '#vcard_form', $html);
    }

    public function onMyVcard4Received()
    {
        Notification::append(null, $this->__('vcard.updated'));
    }

    public function onMyVcard4NotReceived()
    {
        Notification::append(null, $this->__('vcard.not_updated'));
    }

    public function ajaxGetVcard()
    {
        $r = new Get;
        $r->setTo($this->user->id)
          ->request();
    }

    public function ajaxVcardSubmit($vcard)
    {
        $c = $this->user->contact;

        if (Validator::stringType()->length(0, 40)->validate($vcard->name->value)) {
            $c->name    = $vcard->name->value;
            $n = new Nickname;
            $n->setNickname($c->name)
              ->request();
        }

        if (Validator::date('d-m-Y')->validate($vcard->date->value)) {
            $c->date    = $vcard->date->value;
        }

        if (Validator::stringType()->length(0, 40)->validate($vcard->fn->value)) {
            $c->fn      = $vcard->fn->value;
        }

        if (Validator::url()->validate($vcard->url->value)) {
            $c->url     = $vcard->url->value;
        } else {
            $c->url     = '';
        }

        $c->adrlocality     = $vcard->locality->value;
        $c->adrcountry      = $vcard->country->value;

        if (Validator::email()->validate($vcard->email->value)) {
            $c->email   = $vcard->email->value;
        } else {
            $c->email = '';
        }

        if (Validator::stringType()->validate($vcard->desc->value)) {
            $c->description     = trim($vcard->desc->value);
        }

        $c->save();

        $r = new Set;
        $r->setData($c)->request();

        $r = new Moxl\Xec\Action\Vcard\Set;
        $r->setData($vcard)->request();
    }

    public function ajaxChangePrivacy($value)
    {
        if ($value == true) {
            $this->user->setPublic();
            Notification::append(null, $this->__('vcard.public'));
        } else {
            $this->user->setPrivate();
            Notification::append(null, $this->__('vcard.restricted'));
        }
    }

    public function display()
    {
        $this->view->assign('getvcard', $this->call('ajaxGetVcard'));
        $this->view->assign('form', $this->prepareForm($this->user->contact));
    }
}
