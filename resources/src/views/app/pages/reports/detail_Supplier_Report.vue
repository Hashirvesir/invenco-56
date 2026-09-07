<template>
  <div class="main-content">
    <breadcumb :page="$t('SuppliersReport')" :folder="$t('Reports')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-row v-if="!isLoading">
      <!-- ICON BG -->
      <b-col lg="3" md="6" sm="12">
        <b-card class="card-icon-bg card-icon-bg-primary mb-30 text-center">
          <lucide-icon name="shopping-cart" />
          <div class="content">
            <p class="text-muted mt-2 mb-0">{{$t('Purchases')}}</p>
            <p class="text-primary text-24 line-height-1 mb-2">{{provider.total_purchase}}</p>
          </div>
        </b-card>
      </b-col>
      <b-col lg="3" md="6" sm="12">
        <b-card class="card-icon-bg card-icon-bg-primary mb-30 text-center">
          <lucide-icon name="trending-up" />
          <div class="content">
            <p class="text-muted mt-2 mb-0">{{$t('TotalAmount')}}</p>
            <p class="text-primary text-24 line-height-1 mb-2">{{currentUser.currency}} {{formatNumber(provider.total_amount ,2)}}</p>
          </div>
        </b-card>
      </b-col>
      <b-col lg="3" md="6" sm="12">
        <b-card class="card-icon-bg card-icon-bg-primary mb-30 text-center">
          <lucide-icon name="banknote" />
          <div class="content">
            <p class="text-muted mt-2 mb-0">{{$t('TotalPaid')}}</p>
            <p class="text-primary text-24 line-height-1 mb-2">{{ formatPriceWithSymbol(currentUser.currency, provider.total_paid, 2) }}</p>
          </div>
        </b-card>
      </b-col>
      <b-col lg="3" md="6" sm="12">
        <b-card class="card-icon-bg card-icon-bg-primary mb-30 text-center">
          <lucide-icon name="wallet" />
          <div class="content">
            <p class="text-muted mt-2 mb-0">{{$t('Due')}}</p>
            <p class="text-primary text-24 line-height-1 mb-2">{{currentUser.currency}} {{formatNumber((provider.due),2)}}</p>
          </div>
        </b-card>
      </b-col>
    </b-row>

    <!-- Date Range Filter -->
    <b-col md="12" class="text-center mb-3" v-if="!isLoading">
      <date-range-picker
        v-model="dateRange"
        :startDate="startDate"
        :endDate="endDate"
        :time-picker="false"
        :autoApply="true"
        :showDropdowns="true"
        :opens="'right'"
        :drops="'down'"
        @update="Submit_filter_dateRange"
        :locale-data="locale"
      >
        <template v-slot:input="picker" style="min-width: 350px;">
          {{ formatDateTime(picker.startDate) }} — {{ formatDateTime(picker.endDate) }}
        </template>
      </date-range-picker>
    </b-col>

    <b-row v-if="!isLoading">
      <b-col md="12">
        <b-card class="card mb-30" header-bg-variant="transparent ">
          <b-tabs active-nav-item-class="nav nav-tabs" content-class="mt-3">

            <!-- Purchases Table -->
            <b-tab :title="$t('Purchases')">
              <vue-good-table
                mode="remote"
                :columns="columns_purchases"
                :totalRows="totalRows_purchases"
                :rows="purchases"
                @on-page-change="PageChangePurchases"
                @on-per-page-change="onPerPageChangePurchases"
                @on-search="onSearch_purchases"
                :search-options="{
                  placeholder: $t('Search_this_table'),
                  enabled: true,
                }"
                :pagination-options="{
                  enabled: true,
                  mode: 'records',
                  nextLabel: 'next',
                  prevLabel: 'prev',
                }"
                styleClass="tableOne table-hover vgt-table"
              >
                <div slot="table-actions" class="mt-2 mb-3">
                  <b-button @click="printTableOnly('purchases')" size="sm" variant="outline-secondary ripple m-1">
                    <lucide-icon name="printer" /> {{ $t("print") }}
                  </b-button>
                  <b-button @click="Purchase_PDF()" size="sm" variant="outline-success ripple m-1">
                    <lucide-icon name="copy" /> PDF
                  </b-button>
                </div>
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'statut'">
                    <span v-if="props.row.statut == 'received'" class="badge badge-outline-success">{{$t('Received')}}</span>
                    <span v-else-if="props.row.statut == 'pending'" class="badge badge-outline-info">{{$t('Pending')}}</span>
                    <span v-else class="badge badge-outline-warning">{{$t('Ordered')}}</span>
                  </div>
                  <div v-else-if="props.column.field == 'payment_status'">
                    <span v-if="props.row.payment_status == 'paid'" class="badge badge-outline-success">{{$t('Paid')}}</span>
                    <span v-else-if="props.row.payment_status == 'partial'" class="badge badge-outline-primary">{{$t('partial')}}</span>
                    <span v-else class="badge badge-outline-warning">{{$t('Unpaid')}}</span>
                  </div>
                  <div v-else-if="props.column.field == 'Ref'">
                    <router-link :to="'/app/purchases/detail/'+props.row.id">
                      <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
                    </router-link>
                  </div>
                </template>
              </vue-good-table>
            </b-tab>

            <!-- Returns Table -->
            <b-tab :title="$t('Returns')">
              <vue-good-table
                mode="remote"
                :columns="columns_returns"
                :totalRows="totalRows_returns"
                :rows="returns_supplier"
                @on-page-change="PageChangeReturns"
                @on-per-page-change="onPerPageChangeReturns"
                @on-search="onSearch_return_purchases"
                :search-options="{
                  placeholder: $t('Search_this_table'),
                  enabled: true,
                }"
                :pagination-options="{
                  enabled: true,
                  mode: 'records',
                  nextLabel: 'next',
                  prevLabel: 'prev',
                }"
                styleClass="tableOne table-hover vgt-table"
              >
                <div slot="table-actions" class="mt-2 mb-3">
                  <b-button @click="printTableOnly('returns')" size="sm" variant="outline-secondary ripple m-1">
                    <lucide-icon name="printer" /> {{ $t("print") }}
                  </b-button>
                  <b-button @click="Returns_Purchase_PDF()" size="sm" variant="outline-success ripple m-1">
                    <lucide-icon name="copy" /> PDF
                  </b-button>
                </div>
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'statut'">
                    <span v-if="props.row.statut == 'completed'" class="badge badge-outline-success">{{$t('complete')}}</span>
                    <span v-else class="badge badge-outline-info">{{$t('Pending')}}</span>
                  </div>
                  <div v-else-if="props.column.field == 'payment_status'">
                    <span v-if="props.row.payment_status == 'paid'" class="badge badge-outline-success">{{$t('Paid')}}</span>
                    <span v-else-if="props.row.payment_status == 'partial'" class="badge badge-outline-primary">{{$t('partial')}}</span>
                    <span v-else class="badge badge-outline-warning">{{$t('Unpaid')}}</span>
                  </div>
                  <div v-else-if="props.column.field == 'Ref'">
                    <router-link :to="'/app/purchase_return/detail/'+props.row.id">
                      <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
                    </router-link>
                  </div>
                  <div v-else-if="props.column.field == 'purchase_ref' && props.row.purchase_id">
                    <router-link :to="'/app/purchases/detail/'+props.row.purchase_id">
                      <span class="ul-btn__text ml-1">{{props.row.purchase_ref}}</span>
                    </router-link>
                  </div>
                </template>
              </vue-good-table>
            </b-tab>

            <!-- Payments Table -->
            <b-tab :title="$t('PurchaseInvoice')">
              <vue-good-table
                mode="remote"
                :columns="columns_payments"
                :totalRows="totalRows_payments"
                :rows="payments"
                @on-page-change="PageChangePayments"
                @on-per-page-change="onPerPageChangePayments"
                @on-search="onSearch_payments"
                :search-options="{
                  placeholder: $t('Search_this_table'),
                  enabled: true,
                }"
                :pagination-options="{
                  enabled: true,
                  mode: 'records',
                  nextLabel: 'next',
                  prevLabel: 'prev',
                }"
                styleClass="tableOne table-hover vgt-table"
              >
                <div slot="table-actions" class="mt-2 mb-3">
                  <b-button @click="printTableOnly('payments')" size="sm" variant="outline-secondary ripple m-1">
                    <lucide-icon name="printer" /> {{ $t("print") }}
                  </b-button>
                  <b-button @click="Payments_PDF()" size="sm" variant="outline-success ripple m-1">
                    <lucide-icon name="copy" /> PDF
                  </b-button>
                </div>
              </vue-good-table>
            </b-tab>

            <!-- Ledger Tab -->
            <b-tab :title="$t('Ledger') || 'Ledger'">
              <!-- Warehouse Filter -->
              <div class="d-flex align-items-center mb-3 flex-wrap">
                <label class="mb-0 mr-2 font-weight-bold">{{ $t('warehouse') || 'Warehouse' }}:</label>
                <b-form-select
                  v-model="ledger_warehouse_id"
                  size="sm"
                  class="w-auto mr-3"
                  @change="Get_Ledger"
                >
                  <option value="">{{ $t('All') || 'All' }}</option>
                  <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
                </b-form-select>

                <div class="ml-auto">
                  <b-button @click="printTableOnly('ledger')" size="sm" variant="outline-secondary ripple m-1">
                    <lucide-icon name="printer" /> {{ $t("print") }}
                  </b-button>
                  <b-button @click="Ledger_PDF()" size="sm" variant="outline-success ripple m-1">
                    <lucide-icon name="copy" /> PDF
                  </b-button>
                </div>
              </div>

              <div v-if="ledger_loading" class="text-center py-3">
                <div class="spinner spinner-primary"></div>
              </div>

              <div v-else>
                <table class="table table-bordered table-hover table-sm">
                  <thead class="thead-dark">
                    <tr>
                      <th>{{ $t('date') }}</th>
                      <th>{{ $t('Reference') }}</th>
                      <th>{{ $t('Description') || 'Description' }}</th>
                      <th>{{ $t('warehouse') }}</th>
                      <th class="text-right text-danger">{{ $t('Debit') || 'Debit' }}</th>
                      <th class="text-right text-success">{{ $t('Credit') || 'Credit' }}</th>
                      <th class="text-right">{{ $t('Balance') || 'Balance' }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, idx) in ledger" :key="idx"
                        :class="row.type === 'bill' ? 'table-danger-light' : 'table-success-light'">
                      <td>{{ row.date }}</td>
                      <td>{{ row.ref }}</td>
                      <td>
                        <span v-if="row.type === 'bill'" class="badge badge-outline-danger">Bill</span>
                        <span v-else class="badge badge-outline-success">Payment</span>
                        {{ row.description }}
                      </td>
                      <td>{{ row.warehouse_name }}</td>
                      <td class="text-right text-danger">{{ row.debit > 0 ? formatPriceDisplay(row.debit, 2) : '' }}</td>
                      <td class="text-right text-success">{{ row.credit > 0 ? formatPriceDisplay(row.credit, 2) : '' }}</td>
                      <td class="text-right font-weight-bold" :class="row.balance >= 0 ? 'text-danger' : 'text-success'">
                        {{ formatPriceDisplay(Math.abs(row.balance), 2) }}
                        <small v-if="row.balance < 0"> Cr</small>
                        <small v-else-if="row.balance > 0"> Dr</small>
                      </td>
                    </tr>
                    <tr v-if="ledger.length === 0">
                      <td colspan="7" class="text-center text-muted">{{ $t('no_data_available') || 'No data available' }}</td>
                    </tr>
                  </tbody>
                  <tfoot v-if="ledger.length > 0" class="font-weight-bold" style="background:#1a56db;color:#fff;">
                    <tr>
                      <td colspan="4">{{ $t('Total') }}</td>
                      <td class="text-right">{{ formatPriceDisplay(ledger_totals.debit, 2) }}</td>
                      <td class="text-right">{{ formatPriceDisplay(ledger_totals.credit, 2) }}</td>
                      <td class="text-right">
                        {{ formatPriceDisplay(Math.abs(ledger_totals.balance), 2) }}
                        <small v-if="ledger_totals.balance < 0"> Cr</small>
                        <small v-else-if="ledger_totals.balance > 0"> Dr</small>
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </b-tab>
          </b-tabs>
        </b-card>
      </b-col>
    </b-row>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import DateRangePicker from "vue2-daterange-picker";
import moment from "moment";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  components: { DateRangePicker },
  data() {
    return {
      totalRows_purchases: "",
      totalRows_returns: "",
      totalRows_payments: "",
      limit_returns: "10",
      limit_purchases: "10",
      limit_payments: "10",
      purchases_page: 1,
      Return_page: 1,
      Payment_page: 1,
      isLoading: true,
      returns_supplier: [],
      payments: [],
      purchases: [],
      startDate: "",
      endDate: "",
      dateRange: { startDate: "", endDate: "" },
      locale: {
        Label: "Apply",
        cancelLabel: "Cancel",
        weekLabel: "W",
        customRangeLabel: "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1,
      },
      search_purchases: "",
      search_payments: "",
      search_return_purchases: "",
      ledger: [],
      ledger_loading: false,
      ledger_warehouse_id: "",
      warehouses: [],
      provider: {
        id: "",
        name: "",
        total_purchase: 0,
        total_amount: 0,
        total_paid: 0,
        due: 0
      },
      price_format_key: null
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    columns_purchases() {
      return [
        { label: this.$t("Reference"),      field: "Ref",              tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("SupplierBillNo"),   field: "supplier_bill_no",   tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Supplier_Bill_Date") || "Supplier Bill Date", field: "supplier_bill_date", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Supplier"),         field: "provider_name",      tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("warehouse"),      field: "warehouse_name",   tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Total"),          field: "GrandTotal",       type: "decimal", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Paid"),           field: "paid_amount",      type: "decimal", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Due"),            field: "due",              type: "decimal", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Status"),         field: "statut",           tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("PaymentStatus"),  field: "payment_status",   tdClass: "text-left", thClass: "text-left", sortable: false },
      ];
    },
    columns_returns() {
      return [
        { label: this.$t("Reference"),     field: "Ref",           tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Supplier"),      field: "provider_name", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("warehouse"),     field: "warehouse_name",tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Purchase_Ref"),  field: "purchase_ref",  tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Total"),         field: "GrandTotal",    type: "decimal", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Paid"),          field: "paid_amount",   type: "decimal", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Due"),           field: "due",           type: "decimal", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Status"),        field: "statut",        tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("PaymentStatus"), field: "payment_status",tdClass: "text-left", thClass: "text-left", sortable: false },
      ];
    },
    columns_payments() {
      return [
        { label: this.$t("date"),         field: "date",           tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Reference"),    field: "Ref",            tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Purchase"),     field: "purchase_Ref",   tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("ModePaiement"), field: "payment_method", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Account") || "Account", field: "account_name", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Amount"),       field: "montant",        tdClass: "text-left", thClass: "text-left", type: "decimal", sortable: false },
      ];
    },

    ledger_totals() {
      const debit   = this.ledger.reduce((s, r) => s + (r.debit  || 0), 0);
      const credit  = this.ledger.reduce((s, r) => s + (r.credit || 0), 0);
      const balance = this.ledger.length ? this.ledger[this.ledger.length - 1].balance : 0;
      return { debit, credit, balance };
    }
  },

  methods: {

    Submit_filter_dateRange() {
      const start = this.dateRange.startDate ? moment(this.dateRange.startDate) : null;
      const end   = this.dateRange.endDate   ? moment(this.dateRange.endDate)   : null;
      if (start && end) {
        this.startDate = start.format("YYYY-MM-DD");
        this.endDate   = end.format("YYYY-MM-DD");
      }
      this.reloadAll();
    },

    formatDateTime(date) {
      return date ? moment(date).format("YYYY-MM-DD") : "";
    },

    reloadAll() {
      this.Get_Purchases(1);
      this.Get_Returns(1);
      this.Get_Payments(1);
      this.Get_Ledger();
    },

    //---- helper: draw styled banner + filter box into a jsPDF instance ----
    _drawPdfHeader(pdf, title) {
      const self = this;
      const pageW = pdf.internal.pageSize.getWidth();
      const marginX = 40;
      const rtl = (self.$i18n && ["ar", "fa", "ur", "he"].includes(self.$i18n.locale)) ||
                  (typeof document !== "undefined" && document.documentElement.dir === "rtl");

      const filterItems = [];
      if (self.startDate && self.endDate)
        filterItems.push({ label: "Date", value: `${self.startDate}  —  ${self.endDate}` });
      if (self.provider && self.provider.name)
        filterItems.push({ label: "Supplier", value: self.provider.name });
      const hasFilters = filterItems.length > 0;

      return { pageW, marginX, rtl, filterItems, hasFilters };
    },

    //---- shared didDrawPage callback builder ----
    _didDrawPage(pdf, title, filterItems, hasFilters, rtl, marginX) {
      return function(d) {
        const pageW = pdf.internal.pageSize.getWidth();
        const pageH = pdf.internal.pageSize.getHeight();

        // Blue banner
        pdf.setFillColor(26, 86, 219);
        pdf.rect(0, 0, pageW, 60, "F");

        // Title
        pdf.setTextColor(255);
        pdf.setFont("Vazirmatn", "bold");
        pdf.setFontSize(16);
        rtl ? pdf.text(title, pageW - marginX, 38, { align: "right" })
            : pdf.text(title, marginX, 38);

        // Date subtitle in banner
        const dateFilter = filterItems.find(f => f.label === "Date");
        if (dateFilter) {
          pdf.setFontSize(9);
          pdf.setFont("Vazirmatn", "normal");
          rtl ? pdf.text(dateFilter.value, pageW - marginX, 52, { align: "right" })
              : pdf.text(dateFilter.value, marginX, 52);
        }
        pdf.setTextColor(33);

        // Filter box
        if (hasFilters) {
          const lineH = 13;
          const maxW = pageW - 2 * marginX;
          const filterText = filterItems.map(f => `${f.label}: ${f.value}`).join("   |   ");
          const lines = pdf.splitTextToSize(filterText, maxW).slice(0, 3);
          const boxH = 8 + lines.length * lineH + 6;

          pdf.setFillColor(235, 242, 255);
          pdf.rect(0, 62, pageW, boxH, "F");
          pdf.setFont("Vazirmatn", "bold");
          pdf.setFontSize(7);
          pdf.setTextColor(26, 86, 219);
          rtl ? pdf.text("Applied Filters:", pageW - marginX, 72, { align: "right" })
              : pdf.text("Applied Filters:", marginX, 72);
          pdf.setFont("Vazirmatn", "normal");
          pdf.setFontSize(8.5);
          pdf.setTextColor(50, 50, 50);
          lines.forEach((line, i) => {
            rtl ? pdf.text(line, pageW - marginX, 83 + i * lineH, { align: "right" })
                : pdf.text(line, marginX, 83 + i * lineH);
          });
          pdf.setTextColor(33);
        }

        // Page numbers
        pdf.setFontSize(8);
        pdf.setTextColor(100);
        const pn = `${d.pageNumber} / ${pdf.internal.getNumberOfPages()}`;
        rtl ? pdf.text(pn, marginX, pageH - 14, { align: "left" })
            : pdf.text(pn, pageW - marginX, pageH - 14, { align: "right" });
      };
    },

    //---------------------- Purchases PDF -------------------------------\\
    Purchase_PDF() {
      const self = this;
      const pdf = new jsPDF("p", "pt");
      try {
        pdf.addFont("/fonts/Vazirmatn-Bold.ttf", "Vazirmatn", "normal");
        pdf.addFont("/fonts/Vazirmatn-Bold.ttf", "Vazirmatn", "bold");
      } catch (e) {}
      pdf.setFont("Vazirmatn", "normal");

      const { pageW, marginX, rtl, filterItems, hasFilters } = self._drawPdfHeader(pdf, "");
      const title = `${self.$t("Purchases")} — ${self.provider.name || ""}`;

      // Summary totals
      const totalGrand = self.purchases.reduce((s, r) => s + parseFloat(r.GrandTotal || 0), 0);
      const totalPaid  = self.purchases.reduce((s, r) => s + parseFloat(r.paid_amount || 0), 0);
      const totalDue   = self.purchases.reduce((s, r) => s + parseFloat(r.due || 0), 0);

      const columns = [
        { header: self.$t("Reference"),                                dataKey: "Ref" },
        { header: self.$t("SupplierBillNo"),                           dataKey: "supplier_bill_no" },
        { header: self.$t("Supplier_Bill_Date") || "Supplier Bill Date", dataKey: "supplier_bill_date" },
        { header: self.$t("Supplier"),                                 dataKey: "provider_name" },
        { header: self.$t("warehouse"),                                dataKey: "warehouse_name" },
        { header: self.$t("Total"),                                    dataKey: "GrandTotal" },
        { header: self.$t("Paid"),                                     dataKey: "paid_amount" },
        { header: self.$t("Due"),                                      dataKey: "due" },
        { header: self.$t("Status"),                                   dataKey: "statut" },
        { header: self.$t("PaymentStatus"),                            dataKey: "payment_status" },
      ];

      const foot = [[
        self.$t("Total") || "Total", "", "", "", "",
        totalGrand.toFixed(2),
        totalPaid.toFixed(2),
        totalDue.toFixed(2),
        "", "",
      ]];

      autoTable(pdf, {
        columns,
        body: self.purchases,
        foot,
        startY: hasFilters ? 125 : 90,
        theme: "striped",
        margin: { left: marginX, right: marginX },
        styles:             { font: "Vazirmatn", fontSize: 8, cellPadding: 4, halign: rtl ? "right" : "left", textColor: 33 },
        headStyles:         { font: "Vazirmatn", fontStyle: "bold", fillColor: [26, 86, 219], textColor: 255 },
        alternateRowStyles: { fillColor: [245, 247, 250] },
        footStyles:         { font: "Vazirmatn", fontStyle: "bold", fillColor: [26, 86, 219], textColor: 255 },
        didDrawPage: self._didDrawPage(pdf, title, filterItems, hasFilters, rtl, marginX),
      });

      pdf.save(`Purchase_List_${self.provider.name || "supplier"}.pdf`);
    },

    //---------------------- Returns Purchase PDF -----------------------\\
    Returns_Purchase_PDF() {
      const self = this;
      const pdf = new jsPDF("p", "pt");
      try {
        pdf.addFont("/fonts/Vazirmatn-Bold.ttf", "Vazirmatn", "normal");
        pdf.addFont("/fonts/Vazirmatn-Bold.ttf", "Vazirmatn", "bold");
      } catch (e) {}
      pdf.setFont("Vazirmatn", "normal");

      const { pageW, marginX, rtl, filterItems, hasFilters } = self._drawPdfHeader(pdf, "");
      const title = `${self.$t("Returns")} — ${self.provider.name || ""}`;

      const totalGrand = self.returns_supplier.reduce((s, r) => s + parseFloat(r.GrandTotal || 0), 0);
      const totalPaid  = self.returns_supplier.reduce((s, r) => s + parseFloat(r.paid_amount || 0), 0);
      const totalDue   = self.returns_supplier.reduce((s, r) => s + parseFloat(r.due || 0), 0);

      const columns = [
        { header: self.$t("Reference"),    dataKey: "Ref" },
        { header: self.$t("Supplier"),     dataKey: "provider_name" },
        { header: self.$t("warehouse"),    dataKey: "warehouse_name" },
        { header: self.$t("Purchase_Ref"), dataKey: "purchase_ref" },
        { header: self.$t("Total"),        dataKey: "GrandTotal" },
        { header: self.$t("Paid"),         dataKey: "paid_amount" },
        { header: self.$t("Due"),          dataKey: "due" },
        { header: self.$t("Status"),       dataKey: "statut" },
        { header: self.$t("PaymentStatus"),dataKey: "payment_status" },
      ];

      const foot = [[
        self.$t("Total") || "Total", "", "", "",
        totalGrand.toFixed(2),
        totalPaid.toFixed(2),
        totalDue.toFixed(2),
        "", "",
      ]];

      autoTable(pdf, {
        columns,
        body: self.returns_supplier,
        foot,
        startY: hasFilters ? 125 : 90,
        theme: "striped",
        margin: { left: marginX, right: marginX },
        styles:             { font: "Vazirmatn", fontSize: 8, cellPadding: 4, halign: rtl ? "right" : "left", textColor: 33 },
        headStyles:         { font: "Vazirmatn", fontStyle: "bold", fillColor: [26, 86, 219], textColor: 255 },
        alternateRowStyles: { fillColor: [245, 247, 250] },
        footStyles:         { font: "Vazirmatn", fontStyle: "bold", fillColor: [26, 86, 219], textColor: 255 },
        didDrawPage: self._didDrawPage(pdf, title, filterItems, hasFilters, rtl, marginX),
      });

      pdf.save(`Purchase_Returns_${self.provider.name || "supplier"}.pdf`);
    },

    //---------------------- Payments PDF --------------------------------\\
    Payments_PDF() {
      const self = this;
      const pdf = new jsPDF("p", "pt");
      try {
        pdf.addFont("/fonts/Vazirmatn-Bold.ttf", "Vazirmatn", "normal");
        pdf.addFont("/fonts/Vazirmatn-Bold.ttf", "Vazirmatn", "bold");
      } catch (e) {}
      pdf.setFont("Vazirmatn", "normal");

      const { pageW, marginX, rtl, filterItems, hasFilters } = self._drawPdfHeader(pdf, "");
      const title = `${self.$t("PurchaseInvoice")} — ${self.provider.name || ""}`;

      const totalMontant = self.payments.reduce((s, r) => s + parseFloat(r.montant || 0), 0);

      const columns = [
        { header: self.$t("date"),         dataKey: "date" },
        { header: self.$t("Reference"),    dataKey: "Ref" },
        { header: self.$t("Purchase"),     dataKey: "purchase_Ref" },
        { header: self.$t("ModePaiement"), dataKey: "payment_method" },
        { header: self.$t("Amount"),       dataKey: "montant" },
      ];

      const foot = [[
        self.$t("Total") || "Total", "", "", "",
        totalMontant.toFixed(2),
      ]];

      autoTable(pdf, {
        columns,
        body: self.payments,
        foot,
        startY: hasFilters ? 125 : 90,
        theme: "striped",
        margin: { left: marginX, right: marginX },
        styles:             { font: "Vazirmatn", fontSize: 8, cellPadding: 4, halign: rtl ? "right" : "left", textColor: 33 },
        headStyles:         { font: "Vazirmatn", fontStyle: "bold", fillColor: [26, 86, 219], textColor: 255 },
        alternateRowStyles: { fillColor: [245, 247, 250] },
        footStyles:         { font: "Vazirmatn", fontStyle: "bold", fillColor: [26, 86, 219], textColor: 255 },
        didDrawPage: self._didDrawPage(pdf, title, filterItems, hasFilters, rtl, marginX),
      });

      pdf.save(`Payments_${self.provider.name || "supplier"}.pdf`);
    },

    //------------------------------Formatted Numbers -------------------------\\
    formatNumber(number, dec) {
      const value = (typeof number === "string" ? number : number.toString()).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec) return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

    formatPriceDisplay(number, dec) {
      try {
        const decimals = Number.isInteger(dec) ? dec : 0;
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) this.price_format_key = key;
        return formatPriceDisplayHelper(number, decimals, key || null);
      } catch (e) {
        return this.formatNumber(number, dec);
      }
    },

    formatPriceWithSymbol(symbol, number, dec) {
      const safeSymbol = symbol || "";
      const value = this.formatPriceDisplay(number, dec);
      return safeSymbol ? `${safeSymbol} ${value}` : value;
    },

    //------ Print Table Only
    printTableOnly(tableType) {
      let title, rows, columns;
      if (tableType === "purchases") {
        title   = `${this.$t("Reports")} / ${this.$t("SuppliersReport")} / ${this.$t("Purchases")}`;
        rows    = Array.isArray(this.purchases) ? this.purchases : [];
        columns = this.columns_purchases;
      } else if (tableType === "returns") {
        title   = `${this.$t("Reports")} / ${this.$t("SuppliersReport")} / ${this.$t("Returns")}`;
        rows    = Array.isArray(this.returns_supplier) ? this.returns_supplier : [];
        columns = this.columns_returns;
      } else if (tableType === "payments") {
        title   = `${this.$t("Reports")} / ${this.$t("SuppliersReport")} / ${this.$t("PurchaseInvoice")}`;
        rows    = Array.isArray(this.payments) ? this.payments : [];
        columns = this.columns_payments;
      } else if (tableType === "ledger") {
        title   = `${this.$t("Reports")} / ${this.$t("SuppliersReport")} / Ledger`;
        rows    = Array.isArray(this.ledger) ? this.ledger : [];
        columns = [
          { label: this.$t("date"),                      field: "date" },
          { label: this.$t("Reference"),                 field: "ref" },
          { label: this.$t("Description") || "Description", field: "description" },
          { label: this.$t("warehouse"),                 field: "warehouse_name" },
          { label: this.$t("Debit") || "Debit",         field: "debit" },
          { label: this.$t("Credit") || "Credit",       field: "credit" },
          { label: this.$t("Balance") || "Balance",     field: "balance" },
        ];
      } else { return; }

      let tableHTML = '<table style="width:100%;border-collapse:collapse;font-size:10px;">';
      tableHTML += "<thead><tr>";
      columns.forEach(col => {
        tableHTML += `<th style="border:1px solid #ddd;padding:6px 8px;background:#f5f5f5;font-weight:bold;text-align:left;">${col.label}</th>`;
      });
      tableHTML += "</tr></thead><tbody>";
      rows.forEach(row => {
        tableHTML += "<tr>";
        columns.forEach(col => {
          let cellValue = "";
          if (col.field === "statut") {
            if (tableType === "purchases") {
              cellValue = row.statut === "received" ? this.$t("Received") : row.statut === "pending" ? this.$t("Pending") : this.$t("Ordered");
            } else if (tableType === "returns") {
              cellValue = row.statut === "completed" ? this.$t("complete") : this.$t("Pending");
            } else { cellValue = row.statut || ""; }
          } else if (col.field === "payment_status") {
            cellValue = row.payment_status === "paid" ? this.$t("Paid") : row.payment_status === "partial" ? this.$t("partial") : this.$t("Unpaid");
          } else if (["GrandTotal", "paid_amount", "due", "montant"].includes(col.field)) {
            cellValue = this.formatPriceDisplay(row[col.field] || 0, 2);
          } else {
            cellValue = row[col.field] || "";
          }
          tableHTML += `<td style="border:1px solid #ddd;padding:6px 8px;text-align:left;">${cellValue}</td>`;
        });
        tableHTML += "</tr>";
      });
      tableHTML += "</tbody></table>";

      const w = window.open("", "_blank");
      if (!w) { alert("Please allow popups to print"); return; }
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(l => l.outerHTML).join("\n");
      const doc = w.document;
      doc.open();
      doc.write(`<!doctype html><html><head><meta charset="utf-8"/><base href="${window.location.origin}/"/><title>${title}</title>${links}<style>@media print{body,body*{visibility:visible!important;}@page{size:A4 landscape;margin:0.3cm;}}body{margin:0.3cm;font-family:Arial,sans-serif;}.print-header{font-weight:600;margin-bottom:10px;font-size:14px;}</style></head><body><div class="print-header">${title}</div>${tableHTML}</body></html>`);
      doc.close();
      w.focus();
      setTimeout(() => { w.print(); w.close(); }, 400);
    },

    //------------------------------ Show Reports -------------------------\\
    Get_Reports() {
      let id = this.$route.params.id;
      axios
        .get(`report/provider/${id}`)
        .then(response => {
          this.provider = response.data.report;
          this.isLoading = false;
        })
        .catch(() => {
          setTimeout(() => { this.isLoading = false; }, 500);
        });
    },

    PageChangePurchases({ currentPage }) {
      if (this.purchases_page !== currentPage) this.Get_Purchases(currentPage);
    },

    onPerPageChangePurchases({ currentPerPage }) {
      if (this.limit_purchases !== currentPerPage) {
        this.limit_purchases = currentPerPage;
        this.Get_Purchases(1);
      }
    },

    onSearch_purchases(value) {
      this.search_purchases = value.searchTerm;
      this.Get_Purchases(1);
    },

    //--------------------------- Get Purchases By Provider -------------\\
    Get_Purchases(page) {
      axios
        .get("report/provider_purchases", {
          params: {
            page,
            limit:  this.limit_purchases,
            search: this.search_purchases,
            id:     this.$route.params.id,
            from:   this.startDate || "",
            to:     this.endDate   || "",
          }
        })
        .then(response => {
          this.purchases = response.data.purchases;
          this.totalRows_purchases = response.data.totalRows;
          this.isLoading = false;
        })
        .catch(() => { this.isLoading = false; });
    },

    PageChangePayments({ currentPage }) {
      if (this.Payment_page !== currentPage) this.Get_Payments(currentPage);
    },

    onPerPageChangePayments({ currentPerPage }) {
      if (this.limit_payments !== currentPerPage) {
        this.limit_payments = currentPerPage;
        this.Get_Payments(1);
      }
    },

    onSearch_payments(value) {
      this.search_payments = value.searchTerm;
      this.Get_Payments(1);
    },

    //--------------------------- Get Payments By Provider -------------\\
    Get_Payments(page) {
      axios
        .get("/report/provider_payments", {
          params: {
            page,
            limit:  this.limit_payments,
            search: this.search_payments,
            id:     this.$route.params.id,
            from:   this.startDate || "",
            to:     this.endDate   || "",
          }
        })
        .then(response => {
          this.payments = response.data.payments;
          this.totalRows_payments = response.data.totalRows;
        })
        .catch(() => {});
    },

    PageChangeReturns({ currentPage }) {
      if (this.Return_page !== currentPage) this.Get_Returns(currentPage);
    },

    onPerPageChangeReturns({ currentPerPage }) {
      if (this.limit_returns !== currentPerPage) {
        this.limit_returns = currentPerPage;
        this.Get_Returns(1);
      }
    },

    onSearch_return_purchases(value) {
      this.search_return_purchases = value.searchTerm;
      this.Get_Returns(1);
    },

    //--------------------------- Get Returns By Provider -------------\\
    Get_Returns(page) {
      axios
        .get("/report/provider_returns", {
          params: {
            page,
            limit:  this.limit_returns,
            search: this.search_return_purchases,
            id:     this.$route.params.id,
            from:   this.startDate || "",
            to:     this.endDate   || "",
          }
        })
        .then(response => {
          this.returns_supplier = response.data.returns_supplier;
          this.totalRows_returns = response.data.totalRows;
        })
        .catch(() => {});
    },

    //--------------------------- Get Warehouses ---------------------------\\
    Get_Warehouses() {
      axios.get("warehouses", { params: { limit: -1, SortField: "name", SortType: "asc" } })
        .then(response => {
          this.warehouses = response.data.warehouses || [];
        })
        .catch(() => {});
    },

    //--------------------------- Get Supplier Ledger ----------------------\\
    Get_Ledger() {
      this.ledger_loading = true;
      axios
        .get("report/provider_ledger", {
          params: {
            id:           this.$route.params.id,
            warehouse_id: this.ledger_warehouse_id || "",
            from:         this.startDate || "",
            to:           this.endDate   || "",
          }
        })
        .then(response => {
          this.ledger = response.data.ledger || [];
          this.ledger_loading = false;
        })
        .catch(() => { this.ledger_loading = false; });
    },

    //---------------------- Ledger PDF -----------------------------------\\
    Ledger_PDF() {
      const self = this;
      const pdf  = new jsPDF("l", "pt");
      try {
        pdf.addFont("/fonts/Vazirmatn-Bold.ttf", "Vazirmatn", "normal");
        pdf.addFont("/fonts/Vazirmatn-Bold.ttf", "Vazirmatn", "bold");
      } catch (e) {}
      pdf.setFont("Vazirmatn", "normal");

      const { pageW, marginX, rtl, filterItems, hasFilters } = self._drawPdfHeader(pdf, "");
      const title = `Ledger — ${self.provider.name || ""}`;

      const columns = [
        { header: self.$t("date"),                      dataKey: "date" },
        { header: self.$t("Reference"),                 dataKey: "ref" },
        { header: self.$t("Description") || "Description", dataKey: "description" },
        { header: self.$t("warehouse"),                 dataKey: "warehouse_name" },
        { header: self.$t("Debit") || "Debit",         dataKey: "debit_display" },
        { header: self.$t("Credit") || "Credit",       dataKey: "credit_display" },
        { header: self.$t("Balance") || "Balance",     dataKey: "balance_display" },
      ];

      const body = self.ledger.map(r => ({
        date:            r.date,
        ref:             r.ref,
        description:     r.description,
        warehouse_name:  r.warehouse_name,
        debit_display:   r.debit  > 0 ? self.formatPriceDisplay(r.debit,  2) : "",
        credit_display:  r.credit > 0 ? self.formatPriceDisplay(r.credit, 2) : "",
        balance_display: self.formatPriceDisplay(Math.abs(r.balance), 2) + (r.balance < 0 ? " Cr" : r.balance > 0 ? " Dr" : ""),
      }));

      const t = self.ledger_totals;
      const foot = [[
        self.$t("Total") || "Total", "", "", "",
        self.formatPriceDisplay(t.debit,  2),
        self.formatPriceDisplay(t.credit, 2),
        self.formatPriceDisplay(Math.abs(t.balance), 2) + (t.balance < 0 ? " Cr" : t.balance > 0 ? " Dr" : ""),
      ]];

      autoTable(pdf, {
        columns,
        body,
        foot,
        startY: hasFilters ? 125 : 90,
        theme: "striped",
        margin: { left: marginX, right: marginX },
        styles:             { font: "Vazirmatn", fontSize: 8, cellPadding: 4, halign: rtl ? "right" : "left", textColor: 33 },
        headStyles:         { font: "Vazirmatn", fontStyle: "bold", fillColor: [26, 86, 219], textColor: 255 },
        alternateRowStyles: { fillColor: [245, 247, 250] },
        footStyles:         { font: "Vazirmatn", fontStyle: "bold", fillColor: [26, 86, 219], textColor: 255 },
        didDrawPage: self._didDrawPage(pdf, title, filterItems, hasFilters, rtl, marginX),
      });

      pdf.save(`Ledger_${self.provider.name || "supplier"}.pdf`);
    }
  },

  created() {
    this.Get_Reports();
    this.Get_Purchases(1);
    this.Get_Payments(1);
    this.Get_Returns(1);
    this.Get_Ledger();
    this.Get_Warehouses();
  }
};
</script>
