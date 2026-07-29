<?php
namespace Tests\Feature;

use App\Models\OperatingDirective;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatingDirectiveTest extends TestCase
{
    use RefreshDatabase;
    private function userWith(array $permissions):array{$role=Role::create(['name'=>'Vai trò '.uniqid(),'code'=>'R'.uniqid()]);$role->permissions()->attach(Permission::whereIn('code',$permissions)->pluck('id'));$user=User::factory()->create(['is_active'=>true]);$user->roles()->attach($role);return[$user,$user->createToken('test')->plainTextToken];}

    public function test_sender_can_create_and_track_directive():void
    {
        [$sender,$token]=$this->userWith(['directives.send','directives.receive']);$recipient=User::factory()->create(['is_active'=>true]);
        $id=$this->withToken($token)->postJson('/api/v1/directives',['title'=>'Lịch họp giao ban','content'=>'<p><strong>Họp lúc 8 giờ</strong><script>alert(1)</script></p>','recipient_ids'=>[$recipient->id],'is_active'=>true])->assertCreated()->assertJsonPath('data.title','Lịch họp giao ban')->json('data.id');
        $this->assertDatabaseHas('operating_directives',['id'=>$id,'created_by'=>$sender->id]);
        $this->assertStringNotContainsString('<script>',OperatingDirective::findOrFail($id)->content);
        $this->withToken($token)->getJson('/api/v1/directives/sent')->assertOk()->assertJsonPath('data.data.0.recipients_count',1)->assertJsonPath('data.data.0.read_count',0);
    }

    public function test_recipient_sees_unread_and_opening_marks_it_read():void
    {
        [$recipient,$token]=$this->userWith(['directives.receive']);$sender=User::factory()->create(['is_active'=>true]);
        $item=OperatingDirective::create(['created_by'=>$sender->id,'code'=>'DH-01','title'=>'Chỉ đạo mới','content'=>'<p>Nội dung</p>','is_active'=>true]);$item->recipients()->attach($recipient->id);
        $other=OperatingDirective::create(['created_by'=>$sender->id,'code'=>'DH-02','title'=>'Không được nhận','content'=>'<p>Khác</p>','is_active'=>true]);
        $this->withToken($token)->getJson('/api/v1/directives/unread')->assertOk()->assertJsonPath('data.count',1);
        $this->withToken($token)->getJson('/api/v1/directives/inbox')->assertOk()->assertJsonPath('data.data.0.id',$item->id);
        $this->withToken($token)->getJson('/api/v1/directives/'.$other->id)->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/directives/'.$item->id)->assertOk();
        $this->assertDatabaseHas('operating_directive_recipient',['operating_directive_id'=>$item->id,'user_id'=>$recipient->id]);
        $this->withToken($token)->getJson('/api/v1/directives/unread')->assertOk()->assertJsonPath('data.count',0);
    }
}
