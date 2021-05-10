
var ut = {};

// This initializes the main UI bits
ut.initUI = function initUI(cfg) {
    var members       = cfg.members,
        director_name = cfg.director_name,
        
        $last_date  = $('.last-mod-date'),
        
        $summ         = $('.page-summary'),
        $summ_act_q   = $summ.find('.status.act .q'),
        $summ_sem_q   = $summ.find('.status.sem .q'),
        $summ_car_q   = $summ.find('.status.car .q'),
        $summ_unitp_q = $summ.find('.status.unit-prod .q'),
        
        $page_toolbar     = $('.page-toolbar'),
        
        $add_mbr_btn      = $page_toolbar.find('.add-member'),
        $add_mbr_dlg      = $page_toolbar.find('.add-member-dialog'),
        $add_mbr_fn_fld   = $add_mbr_dlg.find('#add-member-firstname'),
        $add_mbr_ln_fld   = $add_mbr_dlg.find('#add-member-lastname'),
        $add_mbr_rc_sel   = $add_mbr_dlg.find('#add-member-recruiter'),
        $add_mbr_rd_fld   = $add_mbr_dlg.find('#add-member-recruit-date'),
        $add_mbr_dlg_btns = $add_mbr_dlg.find('button'),
        
        $add_order_btn    = $page_toolbar.find('.add-order'),
        
        $members_list = $('.members-list'),
        $member_tmpl  = $members_list.find('.unit-member.template'),
        
        $add_order_dlg    = $('.add-order-dialog'),
        $add_ord_name_txt = $add_order_dlg.find('.dialog-title .name'),
        $add_ord_amt_fld  = $add_order_dlg.find('#add-order-amount'),
        $add_ord_dt_fld   = $add_order_dlg.find('#add-order-date'),
        $add_ord_dlg_btns = $add_order_dlg.find('button'),
        curr_order_mbr_id,
        
        o_class = 'open';
    
    // init bits for each member
    $members_list.find('.unit-member').each(function (i, el) {
        
    });
    
    // add-member dialog
    $add_mbr_btn.on('click', function (e) {
        var opening = !$add_mbr_dlg.hasClass(o_class);
        $add_mbr_dlg.toggleClass(o_class);
        if (opening) $add_mbr_fn_fld.focus();
    });
    $add_mbr_dlg
        .on('submit', addMember)
        .on('reset', function (e) {
            $add_mbr_dlg.removeClass(o_class);
        });
    
    // director add-order dialog
    $add_order_btn.on('click', function (e) {
        var bottom = $page_toolbar.position().top + $page_toolbar.height();
        
        $add_ord_name_txt.text(director_name);
        curr_order_mbr_id = 'null';
        $add_order_dlg.css('top', bottom).addClass(o_class);
        $add_ord_amt_fld.focus();
    });
    
    // member delegations
    $members_list
        // expando
        .on('click', '.unit-member', function (e) {
            if (!$(e.target).is('button, input')) $(e.currentTarget).toggleClass('expanded');
        })
        // add-order dialog
        .on('click', '.add-order', function (e) {
            var $summ  = $(e.currentTarget).closest('.details').siblings('.summary'),
                data   = $summ.closest('.unit-member').data(),
                bottom = $summ.offset().top + $summ.height();
            
            $add_ord_name_txt.text(data.member_name);
            curr_order_mbr_id = data.member_id;
            $add_order_dlg.css('top', bottom).addClass(o_class);
            $add_ord_amt_fld.focus();
        })
        // save checkbox flags
        .on('change', 'input[type="checkbox"]', saveFlag);
    $add_order_dlg
        .on('submit', addOrder)
        .on('reset', function (e) {
            curr_order_mbr_id = null;
            $add_order_dlg.removeClass(o_class);
        });
    
    
    // ADD MEMBER FUNCTION
    function addMember(e) {
        var firstname    = ut.trim($add_mbr_fn_fld.val()),
            lastname     = ut.trim($add_mbr_ln_fld.val()),
            recruiter_id = $add_mbr_rc_sel.val(),
            recruit_date = ut.trim($add_mbr_rd_fld.val().replace(/[^-0-9]/g, '')),
            post_data    = {
                'firstname':   firstname,
                'lastname':    lastname,
                'recruit_date':recruit_date
            };
        
        e.preventDefault();
        // check for names
        if (!firstname || !lastname || !recruit_date) {
            ut.notify('Please enter both first and last names and a recruitment date for your new unit member.');
            return;
        }
        // finish post_data
        post_data[cfg.token_id] = cfg.token_value;
        if ('null' !== recruiter_id) post_data.recruiter_id = recruiter_id;
        // proceed
        $add_mbr_dlg_btns.prop('disabled', true);
        ut.processing(true);
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: '/json/add-member.php',
            data: post_data,
            error: function (data) {
                ut.notify('Sorry, there was an error trying to add your new unit member.');
            },
            complete: function (r) {
                $add_mbr_dlg_btns.prop('disabled', false);
                ut.processing(false);
            },
            success: function (r, status, xhr) {
                if (!r.success) ut.notify(r.error);
                else {
                    var $mbr = $member_tmpl.clone().removeClass('template').prependTo($members_list),
                        name = firstname +' '+ lastname;
                    
                    $add_mbr_dlg.trigger('reset');
                    // add to add-member recruiter select
                    $add_mbr_rc_sel.append('<option value="'+ r.member_id +'">'+ name +'</option>');
                    // add to list
                    $mbr.data('member_id', r.member_id).data('member_name', name)
                        .prop('id', 'member-'+ r.member_id)
                        .find('.member-name').text(name);
                }
            }
        });
    }
    
    
    // ADD ORDER FUNCTION
    function addOrder(e) {
        var amount       = parseFloat(ut.trim($add_ord_amt_fld.val().replace(/[^0-9\.]/g, ''))),
            date         = ut.trim($add_ord_dt_fld.val().replace(/[^-0-9]/g, '')),
            post_data    = {
                'amount':   amount.toFixed(2),
                'date':     date,
                'member_id':curr_order_mbr_id
            },
            member       = members[post_data.member_id],
            is_director  = ('null' === post_data.member_id);
        
        e.preventDefault();
        // check for values
        if (!amount || !date) {
            ut.notify('Please enter both an amount and a date for your order.');
            return;
        }
        // finish post_data
        post_data[cfg.token_id] = cfg.token_value;
        // proceed
        $add_ord_dlg_btns.prop('disabled', true);
        ut.processing(true);
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: '/json/add-order.php',
            data: post_data,
            error: function (data) {
                ut.notify('Sorry, there was an error trying to add your order.');
            },
            complete: function (r) {
                $add_ord_dlg_btns.prop('disabled', false);
                ut.processing(false);
            },
            success: function (r, stat, xhr) {
                if (!r.success) ut.notify(r.error);
                else {
                    $add_order_dlg.trigger('reset');
                    if (!is_director) {
                        // recalculate statuses
                        var $mbr     = $members_list.find('#member-'+ post_data.member_id),
                            $act     = $mbr.find('.member-status .a'),
                            $act_gap = $mbr.find('.member-status .gap'),
                            act_gap  = Math.max(0, parseFloat($act_gap.text().replace(/[^\d\.]/g, ''), 10) - amount),
                            $sq      = $mbr.find('.member-sq-status'),
                            $sq_gap  = $sq.find('.gap'),
                            sq_gap   = $sq_gap.length ? Math.max(0, parseFloat($sq_gap.text().replace(/[^\d\.]/g, ''), 10) - amount) : 0;
                        
                        // TODO: calculate everything accurately based on the order date
                        // see if they went active
                        if (!act_gap) {
                            if (!$mbr.hasClass('status-A')) {
                                $summ_act_q.text(parseInt($summ_act_q.text(), 10) + 1);
                            }
                            $mbr.addClass('status-A');
                            $act.text('A1');
                            
                        }
                        if (!act_gap) $act_gap.text('--');
                        else $act_gap.text((act_gap ? '-$' : '')+ act_gap);
                        // see if they went seminar qualified
                        if ($sq_gap.length) {
                            if (!sq_gap) {
                                if (!$sq.hasClass('qualified')) {
                                    $summ_sem_q.text(parseInt($summ_sem_q.text(), 10) + 1);
                                }
                                $sq.addClass('qualified');
                                $sq_gap.text('--');
                            }
                            else $sq_gap.text((sq_gap ? '-$' : '')+ sq_gap);
                        }
                    }
                    // update car production
                    if (is_director || (member && (null == member.recruiter_id))) {
                        var car_gap = parseFloat($summ_car_q.text().replace(/^-\$/, ''));
                        $summ_car_q.text('-$'+ Math.max(0, car_gap - amount).toFixed(2));
                    }
                    // update unit production
                    var unit_prod = parseFloat($summ_unitp_q.text().replace(/^\$/, ''));
                    $summ_unitp_q.text('$'+ (unit_prod + amount).toFixed(2));
                    // update date
                    updateDate(date);
                }
            }
        });
    }
    
    
    // SAVE FLAG FUNCTION
    function saveFlag(e) {
        var $chk   = $(e.currentTarget),
            name   = e.currentTarget.name,
            is_on  = e.currentTarget.checked,
            mbr_id = $chk.closest('.unit-member').data('member_id'),
            post_data    = {
                'member_id':mbr_id
            };
        
        // finish post_data
        post_data[cfg.token_id] = cfg.token_value;
        post_data[name] = is_on ? 1 : 0;
        // proceed
        ut.processing(true);
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: '/json/edit-member.php',
            data: post_data,
            error: function (data) {
                ut.notify('Sorry, there was an error trying to save that unit member.');
                e.currentTarget.checked = !is_on;
            },
            complete: function (r) {
                ut.processing(false);
            },
            success: function (r, status, xhr) {
                if (!r.success) {
                    ut.notify(r.error);
                    e.currentTarget.checked = !is_on;
                }
            }
        });
    }
    
    
    // UPDATE 'LAST UPDATED' DATE
    function updateDate(date) {
        var date_epoch = new Date(date).valueOf(),
            curr_epoch = new Date($last_date.text()).valueOf();
        
        if (date_epoch > curr_epoch) $last_date.text(date);
    }
};


// This displays messages (TODO)
ut.notify = function notify(msg) {
    alert(msg);
};


/* This shows the fake-cursor animation
 *
 * ut.processing();      // show
 * ut.processing(true);  // show
 * ut.processing(false); // hide
 */
ut.processing = (function () {
    var $win  = $(window),
        $doc  = $(document),
        $body = $('body'),
        $processing;
    
    return processing;
    
    // the main processing FN
    function processing(show, e) {
        if (!$processing || !$processing.length) $processing = $('<div>').addClass('processing').appendTo($body);
        if (show || undefined === show) {
            $doc.on('mousemove', followCursor);
            $body.addClass('no-mouse');
            $processing.show();
            followCursor(e || { 'pageX':$win.width()/2, 'pageY':$win.height()/2 });
        }
        else {
            $doc.off('mousemove', followCursor);
            $body.removeClass('no-mouse');
            $processing.hide();
        }
    }
    
    // this receives the mousemove event, making .processing follow the cursor
    function followCursor(e) {
        $processing.css({
            'left': e.pageX + 1,
            'top':  e.pageY + 1
        });
    }
})();


// This trims whitespace off of the beginning and end of a string
ut.trim = function trim(str) {
    return str.replace(/^\s+|\s+$/g, '');
};
