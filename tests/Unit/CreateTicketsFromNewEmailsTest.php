<?php

namespace Tests\Unit;

use App\Jobs\CreateTicketsFromNewEmails;
use App\Requester;
use App\Services\Pop3\FakePop3;
use App\Services\Pop3\FakePop3Message;
use App\Services\Pop3\Pop3;
use App\Ticket;
use App\User;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class CreateTicketsFromNewEmailsTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function does_create_tickets_from_new_emails(){
        Notification::fake();
        $fakePop = new FakePop3();
        $fakePop->messages = [
            new FakePop3Message(["name" => "Bruce Wayne", "email" => "bruce@wayne.com"], "I'm batman", "Why so serious"),
            new FakePop3Message(["name" => "Jack Sparrow", "email" => "jack@sparrow.com"], "The black pearl", "I'm so lost.."),
        ];
        app()->instance(Pop3::class, $fakePop);

        dispatch( new CreateTicketsFromNewEmails() );

        $this->assertEquals(2, Ticket::count());
        tap(Ticket::first(), function($ticket){
            $this->assertEquals("Bruce Wayne", $ticket->requester->name);
            $this->assertEquals("bruce@wayne.com", $ticket->requester->email);
            $this->assertEquals("I'm batman", $ticket->title);
            $this->assertEquals("Why so serious", $ticket->body);
            $this->assertEquals("email", $ticket->tags->first()->name);
        });


    }
    
    /** @test */
    public function does_create_comments_for_new_comment_emails(){
        Notification::fake();
        $fakePop = new FakePop3();
        $agent                              = factory(User::class)     ->create(["email" => "tony@stark.com"]);
        $ticketWithCommentRequester         = factory(Requester::class)->create(["email" => "peter@parker.com"]);
        $ticketThatWillGetTheCommentByMail  = factory(Ticket::class)   ->create(["id" => 18, "requester_id" => $ticketWithCommentRequester ]);
        $ticketReplyBody = "The email reply##- Please type your reply above this line -##</span>\r\n\r\nA new comment for the ticket\r\n\r\nAaaaand another one\r\n\r\nSee the ticket\r\nThank you for using our application!\r\n\r\nticket-id:18.</span>\r\n\r\nRegards,\r\nHandesk\r\n\r\nIf you’re having trouble clicking the \"See the ticket\" button, copy and paste the URL below into your web browser: http://handesk.dev/requester/tickets/eQDXxiSRwPwS0tFGpj9jQJH2\r\n\r\n© 2017 Handesk. All rights reserved. ticket-id:19.";
        $fakePop->messages = [
            new FakePop3Message(["name" => "Jack Sparrow", "email" => "tony@stark.com"], "Reply to of ticket", $ticketReplyBody    ),
            new FakePop3Message(["name" => "Peter Parker", "email" => "peter@parker.com"], "Reply to of ticket 2", $ticketReplyBody    ),
        ];

        app()->instance(Pop3::class, $fakePop);

        dispatch( new CreateTicketsFromNewEmails() );

        $this->assertEquals(1, Ticket::count());

        $this->assertCount(2, $ticketThatWillGetTheCommentByMail->comments);
        tap($ticketThatWillGetTheCommentByMail->comments->first(), function($comment){
            $this->assertEquals("The email reply", $comment->body);
            $this->assertEquals("tony@stark.com", $comment->user->email);
        });

        tap($ticketThatWillGetTheCommentByMail->comments->last(), function($comment){
            $this->assertEquals("The email reply", $comment->body);
            $this->assertNull($comment->user);
        });
    }
}
