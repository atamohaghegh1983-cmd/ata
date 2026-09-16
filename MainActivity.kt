package ir.magpub.order

import android.content.Intent
import android.graphics.Color
import android.graphics.Typeface
import android.net.Uri
import android.os.Bundle
import android.text.InputType
import android.view.Gravity
import android.view.View
import android.widget.*
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import kotlinx.coroutines.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject

class MainActivity : AppCompatActivity() {
    private val client = OkHttpClient()
    private val apiUrl = "https://magpub.ir/wp-json/magpub/v2"
    // این مقدار را با توکن ساخته‌شده در تنظیمات افزونه وردپرس جایگزین کنید.
    private val apiToken = "CHANGE_ME"
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main)
    private val quantities = mutableMapOf<Int, Int>()
    private val prices = mutableMapOf<Int, Double>()
    private val names = mutableMapOf<Int, String>()
    private lateinit var name: EditText
    private lateinit var phone: EditText
    private lateinit var province: Spinner
    private lateinit var city: Spinner
    private lateinit var cityManual: EditText
    private lateinit var address: EditText
    private lateinit var total: TextView
    private lateinit var submit: Button
    private lateinit var shipping: Spinner
    private lateinit var productsBox: LinearLayout
    private var provinces = mutableListOf<Pair<String,String>>()
    private var cities = mutableListOf<Pair<String,String>>()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.decorView.layoutDirection = View.LAYOUT_DIRECTION_RTL
        buildUi()
        if (apiToken != "CHANGE_ME") loadInitialData() else toast("توکن اتصال API هنوز تنظیم نشده است.")
    }

    private fun dp(v:Int)= (v*resources.displayMetrics.density).toInt()
    private fun text(t:String,size:Float,bold:Boolean=false)=TextView(this).apply {
        text=t; textSize=size; setTextColor(Color.rgb(35,35,35)); gravity=Gravity.RIGHT or Gravity.CENTER_VERTICAL
        if(bold) typeface=Typeface.DEFAULT_BOLD
    }
    private fun field(hint:String,input:Int=InputType.TYPE_CLASS_TEXT)=EditText(this).apply {
        this.hint=hint; textSize=16f; inputType=input; setPadding(dp(14),0,dp(14),0)
        layoutParams=LinearLayout.LayoutParams(-1,dp(54)).apply{bottomMargin=dp(10)}
    }
    private fun card()=LinearLayout(this).apply {
        orientation=LinearLayout.VERTICAL; setPadding(dp(16));
        background=android.graphics.drawable.GradientDrawable().apply{setColor(Color.WHITE);cornerRadius=dp(18).toFloat()}
        elevation=dp(3).toFloat(); layoutParams=LinearLayout.LayoutParams(-1,-2).apply{bottomMargin=dp(14)}
    }
    private fun buildUi(){
        val scroll=ScrollView(this).apply{setBackgroundColor(Color.rgb(247,247,249))}
        val root=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL;setPadding(dp(16),dp(18),dp(16),dp(28))}
        root.addView(text("نشر مجله",28f,true))
        root.addView(text("سفارش‌گیر حرفه‌ای فروشگاه",15f).apply{setTextColor(Color.DKGRAY);setPadding(0,0,0,dp(18))})

        val c=card(); c.addView(text("اطلاعات مشتری",19f,true).apply{setPadding(0,0,0,dp(12))})
        name=field("نام و نام خانوادگی")
        phone=field("شماره موبایل",InputType.TYPE_CLASS_PHONE)
        province=Spinner(this)
        city=Spinner(this)
        cityManual=field("شهر (در صورت نبودن در فهرست)")
        address=field("آدرس کامل")
        address.minLines=3; address.gravity=Gravity.TOP or Gravity.RIGHT; address.setPadding(dp(14),dp(12),dp(14),dp(12))
        c.addView(name); c.addView(phone)
        c.addView(label("استان")); c.addView(province,LinearLayout.LayoutParams(-1,dp(54)).apply{bottomMargin=dp(10)})
        c.addView(label("شهر")); c.addView(city,LinearLayout.LayoutParams(-1,dp(54)).apply{bottomMargin=dp(10)})
        c.addView(cityManual); c.addView(address); root.addView(c)

        val p=card(); p.addView(text("محصولات",19f,true).apply{setPadding(0,0,0,dp(12))})
        productsBox=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL}; p.addView(productsBox); root.addView(p)

        val s=card(); s.addView(text("نحوه ارسال",19f,true).apply{setPadding(0,0,0,dp(8))})
        shipping=Spinner(this); shipping.adapter=ArrayAdapter(this,android.R.layout.simple_spinner_dropdown_item,arrayOf("در حال دریافت…")); s.addView(shipping); root.addView(s)

        val sum=card(); sum.addView(text("خلاصه سفارش",19f,true).apply{setPadding(0,0,0,dp(8))})
        total=text("مبلغ نهایی: ۰ تومان",20f,true); sum.addView(total); root.addView(sum)

        submit=Button(this).apply{text="ثبت سفارش و دریافت لینک پرداخت";textSize=17f;isAllCaps=false;setTextColor(Color.WHITE)
            background=android.graphics.drawable.GradientDrawable().apply{setColor(Color.rgb(30,136,229));cornerRadius=dp(16).toFloat()}}
        root.addView(submit,LinearLayout.LayoutParams(-1,dp(58))); submit.setOnClickListener{createOrder()}
        scroll.addView(root); setContentView(scroll)
    }
    private fun label(s:String)=text(s,13f,true).apply{setTextColor(Color.DKGRAY);setPadding(0,0,0,dp(4))}

    private fun authRequest(url:String, method:String="GET", body:String?=null): Request {
        val b=Request.Builder().url(url).addHeader("Authorization","Bearer $apiToken")
        if(method=="POST") b.post((body?:("{}")).toRequestBody("application/json".toMediaType())) else b.get()
        return b.build()
    }

    private fun loadInitialData(){
        scope.launch(Dispatchers.IO){
            try {
                val prodRes=client.newCall(authRequest("$apiUrl/products")).execute()
                val prodJson=JSONObject(prodRes.body?.string()?:("{}"))
                val locRes=client.newCall(authRequest("$apiUrl/locations")).execute()
                val locJson=JSONObject(locRes.body?.string()?:("{}"))
                withContext(Dispatchers.Main){
                    if(prodRes.isSuccessful) loadProductJson(prodJson) else toast(prodJson.optString("message","دریافت محصولات ناموفق بود"))
                    if(locRes.isSuccessful) loadLocationsJson(locJson) else toast("دریافت استان‌ها ناموفق بود")
                }
            } catch(e:Exception){ withContext(Dispatchers.Main){toast("خطا در اتصال به magpub.ir")} }
        }
    }
    private fun loadProductJson(j:JSONObject){
        val arr=j.optJSONArray("products")?:JSONArray(); productsBox.removeAllViews()
        for(i in 0 until arr.length()){
            val x=arr.getJSONObject(i); val id=x.optInt("id"); names[id]=x.optString("name"); prices[id]=x.optDouble("price",0.0)
            productsBox.addView(productRow(id,names[id]!!))
        }
    }
    private fun loadLocationsJson(j:JSONObject){
        provinces.clear(); val a=j.optJSONArray("provinces")?:JSONArray()
        for(i in 0 until a.length()){val x=a.getJSONObject(i); provinces.add(Pair(x.optString("id"),x.optString("name")))}
        province.adapter=ArrayAdapter(this,android.R.layout.simple_spinner_dropdown_item,provinces.map{it.second})
        province.onItemSelectedListener=object:AdapterView.OnItemSelectedListener{
            override fun onNothingSelected(parent:AdapterView<*>?) {}
            override fun onItemSelected(parent:AdapterView<*>?,view:View?,position:Int,id:Long){ if(position in provinces.indices) loadCities(provinces[position].first) }
        }
        if(provinces.isNotEmpty()) loadCities(provinces[0].first)
        val sh=j.optJSONArray("shipping_methods")?:JSONArray(); val ss=mutableListOf<String>(); for(i in 0 until sh.length()) ss.add(sh.optString(i))
        if(ss.isEmpty()) ss.addAll(listOf("حضوری","پیک","تاپین","باربری")); shipping.adapter=ArrayAdapter(this,android.R.layout.simple_spinner_dropdown_item,ss)
    }
    private fun loadCities(state:String){
        scope.launch(Dispatchers.IO){try{
            val res=client.newCall(authRequest("$apiUrl/locations?state=${Uri.encode(state)}")).execute(); val j=JSONObject(res.body?.string()?:("{}")); val a=j.optJSONArray("cities")?:JSONArray(); val list=mutableListOf<Pair<String,String>>(); for(i in 0 until a.length()){ val x=a.optJSONObject(i); if(x!=null) list.add(Pair(x.optString("id"),x.optString("name"))) }
            withContext(Dispatchers.Main){cities=list; val shown=if(list.isEmpty()) listOf("شهر را دستی وارد کنید") else list.map{it.second}; city.adapter=ArrayAdapter(this@MainActivity,android.R.layout.simple_spinner_dropdown_item,shown); cityManual.visibility=if(list.isEmpty()) View.VISIBLE else View.GONE}
        }catch(_:Exception){withContext(Dispatchers.Main){cityManual.visibility=View.VISIBLE}}}
    }
    private fun selectedCity():String { val manual=cityManual.text.toString().trim(); if(manual.isNotBlank()) return manual; return if(cities.isNotEmpty()) city.selectedItem?.toString()?:("") else "" }

    private fun productRow(id:Int,title:String):View{
        val row=LinearLayout(this).apply{orientation=LinearLayout.HORIZONTAL;gravity=Gravity.CENTER_VERTICAL;setPadding(0,dp(7),0,dp(7))}
        val info=LinearLayout(this).apply{orientation=LinearLayout.VERTICAL}; info.addView(text(title,16f,true)); info.addView(text("قیمت: ${money(prices[id]?:0.0)} تومان",12f).apply{setTextColor(Color.GRAY)})
        val minus=Button(this).apply{text="−";textSize=20f;isAllCaps=false;minWidth=dp(48)}; val qty=text("۰",18f,true).apply{gravity=Gravity.CENTER;minWidth=dp(42)}; val plus=Button(this).apply{text="+";textSize=20f;isAllCaps=false;minWidth=dp(48)}
        minus.setOnClickListener{changeQty(id,-1,qty)}; plus.setOnClickListener{changeQty(id,1,qty)}; row.addView(info,LinearLayout.LayoutParams(0,-2,1f)); row.addView(minus);row.addView(qty);row.addView(plus);return row
    }
    private fun changeQty(id:Int,d:Int,v:TextView){val q=((quantities[id]?:0)+d).coerceAtLeast(0);quantities[id]=q;v.text=persian(q.toString());updateTotal()}
    private fun updateTotal(){var t=0.0; quantities.forEach{(id,q)->t+=(prices[id]?:0.0)*q}; total.text="مبلغ نهایی: ${money(t)} تومان"}
    private fun money(v:Double)=persian(String.format("%,.0f",v))
    private fun persian(s:String)=s.map{when(it){'0'->'۰';'1'->'۱';'2'->'۲';'3'->'۳';'4'->'۴';'5'->'۵';'6'->'۶';'7'->'۷';'8'->'۸';'9'->'۹';else->it}}.joinToString("")

    private fun createOrder(){
        if(apiToken=="CHANGE_ME"){toast("توکن اتصال API هنوز تنظیم نشده است.");return}
        if(name.text.isBlank()||phone.text.length<10){toast("نام و شماره موبایل را کامل کنید.");return}
        val items=JSONArray(); quantities.filterValues{it>0}.forEach{(id,q)->items.put(JSONObject().put("product_id",id).put("quantity",q))}
        if(items.length()==0){toast("حداقل یک محصول انتخاب کنید.");return}
        submit.isEnabled=false; submit.text="در حال ایجاد سفارش…"
        val state=if(provinces.isNotEmpty() && province.selectedItemPosition>=0) provinces[province.selectedItemPosition].first else ""
        val payload=JSONObject().apply{put("customer",JSONObject().apply{put("first_name",name.text.toString());put("phone",phone.text.toString());put("state",state);put("city",selectedCity());put("address",address.text.toString())});put("items",items);put("shipping_method",shipping.selectedItem?.toString()?:(""))}
        scope.launch(Dispatchers.IO){try{
            val res=client.newCall(authRequest("$apiUrl/order","POST",payload.toString())).execute(); val j=JSONObject(res.body?.string()?:("{}"))
            withContext(Dispatchers.Main){submit.isEnabled=true;submit.text="ثبت سفارش و دریافت لینک پرداخت"
                if(res.isSuccessful){val url=j.optString("payment_url"); val order=j.optString("order_number",j.optString("order_id")); AlertDialog.Builder(this@MainActivity).setTitle("سفارش ثبت شد").setMessage("شماره سفارش: #${persian(order)}\nمبلغ: ${money(j.optDouble("total"))} تومان").setPositiveButton("پرداخت آنلاین"){_,_->if(url.isNotBlank())startActivity(Intent(Intent.ACTION_VIEW,Uri.parse(url)))}.setNegativeButton("بستن",null).show()}
                else toast(j.optString("message","خطا در ثبت سفارش"))
            }
        }catch(e:Exception){withContext(Dispatchers.Main){submit.isEnabled=true;submit.text="ثبت سفارش و دریافت لینک پرداخت";toast("خطا در اتصال به سایت")}}}
    }
    private fun toast(s:String)=Toast.makeText(this,s,Toast.LENGTH_LONG).show()
    override fun onDestroy(){scope.cancel();super.onDestroy()}
}
