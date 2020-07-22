<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\ProductCategory;
use App\ProjectHasProductCategory;
use DataTables;
use Form;
use App\ProjectType;
use App\SubContractor;
use App\Project;
use App\ProjectEnquiry;
use Carbon\Carbon;
use App\Architect;
use App\User;
use App\Financier;
use App\Interior;
use App\MechanicalEngineer;
use App\Quantity;
use App\Contractor;
use Auth;
use App\Client;
use App\EnquiryRemarks;


class Reports extends Controller
{
    /**
     * this function for salesman reports
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function enquiryReports(Request $request){
        try {
            $id=$request->id;
            $sales=User::with('roles')->whereHas('roles', function($query){
                $query->where('id',config('constants.ROLE_TYPE_SALES_ID'));
            })->where(['is_active'=>TRUE])->pluck('name', 'id');
            $projecttype = ProjectType::where(['is_active'=>TRUE])->pluck('title', 'id');
            $productcategory=ProductCategory::where(['is_active'=>TRUE])->pluck('title', 'id');
            return view('admin.reports.enquiryreports',compact('id','sales','projecttype','productcategory'));
        } catch (Exception $e) {

        }  
    }

     /**
     * this function for salesman reports
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getEnquiryReports(Request $request){
         try {
            $projectenquiry = ProjectEnquiry::query()->with('getproductcategory','getremarks','getProject')->select([\DB::raw(with(new ProjectEnquiry)->getTable().'.*')])->groupBy('id');

            if($request->input('id') && $request->id=!''){
               $projectenquiry->where('project_id',$request->id);
            }
           
            $product_category_id = intval($request->input('product_category_id'));
            if(intval($product_category_id) > 0){
                $projectenquiry->where('product_category_id',$product_category_id);              
            }

            $sales_id = intval($request->input('sales_id'));
            if(intval($sales_id) > 0){
                $projectenquiry->whereHas('getProject', function($query) use ($sales_id) { 
                    $query->where('created_by',$sales_id);              
                }); 
            }

            $type_id = intval($request->input('type_id'));
            if(intval($type_id) > 0){
                $projectenquiry->whereHas('getProject', function($query) use ($type_id) { 
                    $query->where('project_type_id',$type_id);              
                }); 
            }

            $source =$request->input('source');
            if($source!=''){
                $projectenquiry->where('enq_source_type',$source);              
            }
           
            $status = $request->input('status');
            if($status !=''){
                if($status=='live'){
                      $projectenquiry->where('won_loss',null);  
                }else{
                     $projectenquiry->where('won_loss',$status);    
                }
            }
            
            $expected_date = $request->input('expected_date');
            if($expected_date!=''){
               $expected_date = date('Y-m-d', strtotime($expected_date));
               $projectenquiry->whereDate('expected_date', '>=', $expected_date);
            } 
            
            $received_date = $request->input('received_date');
            if($received_date!=''){
               $received_date = date('Y-m-d', strtotime($received_date));
               $projectenquiry->whereDate('received_date', '<=', $received_date);
            } 

            return  DataTables::of($projectenquiry)
            ->editColumn('expected_date', function ($project){
                $expected_date=$project->expected_date?$project->expected_date:date('Y-m-d');
                return  date('d M Y', strtotime($expected_date));  
            })
            ->editColumn('enq_source_type', function ($project){
                return  isset($project->enq_source_type)?ucwords($project->enq_source_type):'';
            })
            ->editColumn('getProject.created_by', function ($project){
                return  isset($project->getProject->users->name)?ucwords($project->getProject->users->name):'';
            })
            ->editColumn('getProject.project_name', function ($project){
                return  isset($project->getProject->project_name)?ucwords($project->getProject->project_name):'';
            })
            ->editColumn('getProject.project_type_id', function ($project){
                return  isset($project->getProject->getprojecttype->title)?ucwords($project->getProject->getprojecttype->title):'';
            })
            ->editColumn('enq_source_type', function ($project){
            	$enq_source_type=isset($project->enq_source_type)?$project->enq_source_type:'';
            	if($enq_source_type=='quantity'){
           				return 'Quantity Surveyor';
            	}else if($enq_source_type=='engineer'){
					       return 'Mechanical Engineer';
            	}
            		return  isset($project->enq_source_type)?ucwords($project->enq_source_type):'';
            })
            ->editColumn('enq_source', function ($project){
            	$enq_source=isset($project->enq_source)?$project->enq_source:'';
            	$enq_source_type=isset($project->enq_source_type)?$project->enq_source_type:'';
 				      $people_list='';
                
                if($enq_source_type == 'client'){
                    $people_list=Client::where('id',$enq_source)->where('is_active',1)->select('name')->first();
                }elseif($enq_source_type== 'financier'){
                    $people_list=Financier::where('id',$enq_source)->where('is_active',1)->select('name')->first();
                }elseif($enq_source_type== 'quantity'){
                   $people_list=Quantity::where('id',$enq_source)->where('is_active',1)->select('name')->first();
                }elseif($enq_source_type== 'engineer'){
                   $people_list=MechanicalEngineer::where('id',$enq_source)->where('is_active',1)->select('name')->first();
                }elseif($enq_source_type== 'architect'){
                   $people_list=Architect::where('id',$enq_source)->where('is_active',1)->select('name')->first();
                }elseif($enq_source_type== 'interior'){
                   $people_list=Interior::where('id',$enq_source)->where('is_active',1)->select('name')->first();
                }elseif($enq_source_type== 'contractor'){
                    $people_list=Contractor::where('id',$enq_source)->where('is_active',1)->select('name')->first();
                }
                return  $people_list['name'];
            })
            ->editColumn('received_date', function ($project){
                $received_date=$project->received_date?$project->received_date:'';
                if($received_date!=''){
                    return  date('d M Y', strtotime($received_date));  
                }
                return '';
            })
            ->editColumn('won_loss', function ($project){
                $won_loss=$project->won_loss?$project->won_loss:'';
                if($won_loss==''){
                   return 'Live';
                }
                return $won_loss;
            })
            ->editColumn('quotation_date', function ($project){
                $quotation_date=$project->quotation_date?$project->quotation_date:'';
                if($quotation_date!=''){
                    return date('d M Y', strtotime($quotation_date)); 
                }
                return  '';
            })->editColumn('remarks', function ($project){
                return $project->remarks;
            })->addColumn('action', function ($projectenquiry) {
               return    '<a href="'.route('admin.reports.enquirydetails',[$projectenquiry->id]).'" class="btn btn-secondary btn-circle btn-sm"><i class="fas fa-eye" aria-hidden="true"></i></a>';
           })->rawColumns(['action'])->make(true);
        } catch (Exception $e) {

        }  
    }

    /**
     * this function for salesman reports
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function salesManReports(Request $request){
        try {
            $id=$request->id;
            $sales=User::with('roles')->whereHas('roles', function($query){
                $query->where('id',config('constants.ROLE_TYPE_SALES_ID'));
            })->where(['is_active'=>TRUE])->pluck('name', 'id');
            $projecttype = ProjectType::where(['is_active'=>TRUE])->pluck('title', 'id');
            $productcategory=ProductCategory::where(['is_active'=>TRUE])->pluck('title', 'id');
            return view('admin.reports.salesmanreports',compact('id','sales','projecttype','productcategory'));
        } catch (Exception $e) {

        }  
    }

    /**
     * this function for salesman reports
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getSalesManReports(Request $request){
        try {
            
            $projectenquiry = ProjectEnquiry::query()->with('getproductcategory','getremarks','getProject'); 
            if(in_array(Auth::user()->roles->first()->id, [config('constants.ROLE_TYPE_SALES_ID')])) {
                $projectenquiry->whereHas('getProject', function($query){ 
                    $query->where('created_by',Auth::user()->id);              
                });
            }
            $projectenquiry->select([\DB::raw(with(new ProjectEnquiry)->getTable().'.*')])->groupBy('id');  

            if($request->input('id') && $request->id=!''){
               $projectenquiry->where('project_id',$request->id);
            }
           
            $product_category_id = intval($request->input('product_category_id'));
            if(intval($product_category_id) > 0){
                $projectenquiry->where('product_category_id',$product_category_id);              
            }

            $sales_id = intval($request->input('sales_id'));
            if(intval($sales_id) > 0){
                $projectenquiry->whereHas('getProject', function($query) use ($sales_id) { 
                    $query->where('created_by',$sales_id);              
                }); 
            }

            $type_id = intval($request->input('type_id'));
            if(intval($type_id) > 0){
                $projectenquiry->whereHas('getProject', function($query) use ($type_id) { 
                    $query->where('project_type_id',$type_id);              
                }); 
            }

            $source =$request->input('source');
            if($source!=''){
                $projectenquiry->where('enq_source_type',$source);              
            }
           
            $status = $request->input('status');
            if($status !=''){
                if($status=='live'){
                      $projectenquiry->where('won_loss',null);  
                }else{
                     $projectenquiry->where('won_loss',$status);    
                }
            }
            
            $expected_date = $request->input('expected_date');
            if($expected_date!=''){
               $expected_date = date('Y-m-d', strtotime($expected_date));
               $projectenquiry->whereDate('expected_date', '>=', $expected_date);
            } 
            
            $received_date = $request->input('received_date');
            if($received_date!=''){
               $received_date = date('Y-m-d', strtotime($received_date));
               $projectenquiry->whereDate('received_date', '<=', $received_date);
            } 

            return  DataTables::of($projectenquiry)
            ->editColumn('expected_date', function ($project){
                $expected_date=$project->expected_date?$project->expected_date:date('Y-m-d');
                return  date('d M Y', strtotime($expected_date));  
            })
            ->editColumn('enq_source_type', function ($project){
                return  isset($project->enq_source_type)?ucwords($project->enq_source_type):'';
            })
            ->editColumn('getProject.created_by', function ($project){
                return  isset($project->getProject->users->name)?ucwords($project->getProject->users->name):'';
            })
            ->editColumn('getProject.project_name', function ($project){
                return  isset($project->getProject->project_name)?ucwords($project->getProject->project_name):'';
            })
            ->editColumn('getProject.project_type_id', function ($project){
                return  isset($project->getProject->getprojecttype->title)?ucwords($project->getProject->getprojecttype->title):'';
            })
            ->editColumn('received_date', function ($project){
                $received_date=$project->received_date?$project->received_date:'';
                if($received_date!=''){
                    return  date('d M Y', strtotime($received_date));  
                }
                return '';
            })
            ->editColumn('won_loss', function ($project){
                $won_loss=$project->won_loss?$project->won_loss:'';
                if($won_loss==''){
                   return 'Live';
                }
                return $won_loss;
            })
            ->editColumn('quotation_date', function ($project){
                $quotation_date=$project->quotation_date?$project->quotation_date:'';
                if($quotation_date!=''){
                    return date('d M Y', strtotime($quotation_date)); 
                }
                return  '';
            })->editColumn('remarks', function ($project){
                return $project->remarks;
            })->addColumn('action', function ($projectenquiry) {
                   $html='';
                    if(Auth::user()->roles->first()->id != config('constants.ROLE_TYPE_SUPERADMIN_ID')){  
                        $html.='<a href="'.route('admin.projects.editenquiry',[$projectenquiry->id]).'" class="btn btn-success btn-circle btn-sm"><i class="fas fa-edit"></i></a> <a href="'.route('admin.projects.addremarks',[$projectenquiry->id]).'" class="btn btn-info btn-circle btn-sm"><i class="fa fa-plus" aria-hidden="true"></i></a> <a href="'.route('admin.projects.viewremark',[$projectenquiry->id]).'" class="btn btn-secondary btn-circle btn-sm"><i class="fas fa-eye" aria-hidden="true"></i></a>';
                    }else{
                        $html.= '<a href="'.route('admin.projects.viewremark',[$projectenquiry->id]).'" class="btn btn-secondary btn-circle btn-sm"><i class="fas fa-eye" aria-hidden="true"></i></a>';
                    }
            return $html;

           })->rawColumns(['action'])->make(true);
        } catch (Exception $e) {

        }  
    }

    /**
     * this function for salesman reports
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
      public function enquiryDetails($id){
        $remarks = ProjectEnquiry::with('getremarks','getProject')->where('id',$id)->first();
        $project_id=isset($remarks->getProject->id)?$remarks->getProject->id:'';
        $productcategories=ProjectHasProductCategory::with('category')->where('project_id',$project_id)->get();
        return view('admin.reports.enquirydetails',compact('remarks','productcategories'));
    }

}